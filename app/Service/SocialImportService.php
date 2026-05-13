<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Social\SocialItem;
use App\Model\Orm;
use Nette\Utils\Image;
use Nette\Utils\ImageException;
use Nextras\Orm\Collection\ICollection;

class SocialImportService
{
    private const API_VERSION  = 'v19.0';
    private const ACCOUNT_ID   = '17841400000000000';
    private const ACCESS_TOKEN = 'EAAxxxxxxx';

    private const APP_ID = '960071293541901';
    private const APP_SECRET = '50ac134d4be54d59ae20f0abe8bb46e0';

    public function __construct(
        private readonly Orm $orm,
    ) {}

    public function import(int $limit = 20): void
    {
        $posts    = $this->fetchFromApi($limit);
        $imported = 0;

        foreach ($posts as $post) {
            if ($this->orm->socialItems->getByExternalId($post['id']) !== null) {
                continue;
            }

            $item               = new SocialItem();
            $item->externalId   = $post['id'];
            $item->mediaType    = $post['media_type'];
            $item->mediaUrl     = $post['media_url']     ?? null;
            $item->thumbnailUrl = $post['thumbnail_url'] ?? null;
            $item->caption      = $post['caption']       ?? null;
            $item->permalink    = $post['permalink'];
            $item->publishedAt  = new \DateTimeImmutable($post['timestamp']);

            $this->orm->persistAndFlush($item);
            $imported++;
        }

        echo "Import done. Total: $imported new posts.\n";
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchFromApi(int $limit): array
    {
        $fields = implode(',', [
            'id',
            'media_type',
            'media_url',
            'thumbnail_url',
            'caption',
            'permalink',
            'timestamp',
        ]);

        $url = sprintf(
            'https://graph.facebook.com/%s/%s/media?fields=%s&limit=%d&access_token=%s',
            self::API_VERSION,
            urlencode($this->accountId),
            urlencode($fields),
            $limit,
            urlencode($this->accessToken),
        );

        $context = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'timeout'       => 10,
                'ignore_errors' => true,
            ],
        ]);

        $response = file_get_contents($url, false, $context);

        if ($response === false) {
            throw new \RuntimeException('Failed to reach Instagram Graph API.');
        }

        $json = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        if (isset($json['error'])) {
            throw new \RuntimeException(
                sprintf('Instagram API error [%s]: %s', $json['error']['code'], $json['error']['message'])
            );
        }

        return $json['data'] ?? [];
    }


    /**
     * HOMECREDIT IG IMPORT
     * @return bool
     */
    public function importInstagram() : bool
    {
        try {
            $newestItem = $this->orm->articleItem
                ->orderBy('createdAt', ICollection::DESC)
                ->limitBy(1)
                ->fetch();

            $since = $newestItem ? $newestItem->createdAt->getTimestamp() + 1 : 1576108800;

            $created = 0;

            $accessToken = '1015553598.1677ed0.2077e2dcb8cc4d55af5d41e3d3b9dac0';
            $igAccountId = '267071716188';
            $url = 'https://graph.facebook.com/v23.0/' . $this::ACCOUNT_ID . '/feed?fields=media_url,caption,id,timestamp&limit=8&since=' . $since . '&access_token=' . $this::ACCESS_TOKEN;

            $response = file_get_contents($url);
            $posts = json_decode($response, true)['data'];

            foreach ($posts as $post) {
                if (!isset($post['caption'])) {
                    continue;
                }

                $entity = [
                    'thumbnailUrl' => $this->getImage(
                        $post['media_url'],
                        $post['id']
                    ),
//                    'mediaType' => $post['media_type'], // ???
                    'caption' => $post['caption'],
                    'mediaUrl' => 'https://www.instagram.com/p/' . $post['id'],
                    //'permalink' => 'https://www.instagram.com/p/' . $post['id'], // the fuck is this???
                    'createdAt' => $post['timestamp'],
                    'publishedAt' => $post['timestamp'],
                    'externalId' => $post['id'],
                    'isActive' => 1
                ];

                $itemId = $this->orm->socialItem->insertEntity(null, $entity);

//                $entityTrans = [
//                    'name' => 'Instagram post - ' . $post['id'],
//                    'annotation' => $post['caption'],
//                    'mainContent' => null,
//                    'seoUrl' => 'https://www.instagram.com/p/' . $post['id'],
//                    'lang' => 'cs',
//                    'articleItem' => $itemId
//                ];
//
//                $this->orm->articleItemTrans->insertEntity(null, $entityTrans);
                $created++;
            }

            return $created > 0;
        } catch (\Exception $e) {
            bdump($e);
            return false;
        }
    }

    /**
     * @param array $item
     * @param string $type
     * @return string
     * @throws ImageException
     */
    public function getImage(string $img, string $id, string $type) : string
    {
        $fileName = "ig_" . $id . ".jpg";

        $dir = WEB_DIR . '/social_images';
        if (!file_exists($dir)) {
            mkdir($dir);
        }

        if ($img) {
            $source = $this->curlGet($img);
            if ($source) {
                $image = Image::fromString($source);
                $height = $image->getHeight();
                $width = $image->getWidth();

                $file = $dir . '/' . $fileName;

                // TRICK TO NOT DOWNLOAD 1x1 BLANK IMAGES WHICH FACEBOOK SOMETIMES OFFERS
                if ($width > 1 && $height > 1) {
                    if (file_exists($file)) {
                        return $fileName;
                    } else {
                        $image->resize(700, 350, Image::FILL);
                        $image->save($file, 100, Image::JPEG);
                    }
                    return $fileName;
                }
            }
        }

        return '';
    }


    /**
     * @param string $url
     * @return string
     */
    public function curlGet(string $url) : string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);

        $result = curl_exec($ch);
        if ($result === false) {
            $error = curl_error($ch);  // Get the error message
            $errorCode = curl_errno($ch);  // Get the error code
            echo "cURL error ({$errorCode}): {$error}\n";
        }

        curl_close($ch);

        return $result;
    }
}