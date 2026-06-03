<?php declare(strict_types=1);

namespace App\Presentation\Car;

use App\Components\Forms\CarInquiryFormControl;
use App\Components\Forms\CarInquiryFormControlFactory;
use App\Model\Car\Car;
use App\Model\CarEquipment\CarEquipmentRepository;
use App\Model\Orm;
use Nette\Application\UI\Presenter;

class CarPresenter extends Presenter
{
    private string $lang;
    private ?Car $currentCar = null;

    /** @persistent */
    public string $order = 'DESC';

    /** @persistent */
    public int $page = 1;

    public int $numOfPages = 1;

    public int $limit = 6;

    /** @persistent */
    public ?string $znacka = null;

    /** @persistent */
    public ?string $karoserie = null;

    /** @persistent */
    public ?string $palivo = null;

    /** @persistent */
    public ?string $stitek = null;

    /** @persistent */
    public ?int $priceFrom = null;

    /** @persistent */
    public ?int $priceTo = null;

    /** @persistent */
    public ?int $yearFrom = null;

    /** @persistent */
    public ?int $yearTo = null;

    /** @persistent */
    public ?int $kmFrom = null;

    /** @persistent */
    public ?int $kmTo = null;

    /** @persistent */
    public ?string $odpocet = null;

    public function __construct(
        private readonly Orm $orm,
        private readonly CarEquipmentRepository $carEquipmentRepository,
        private readonly CarInquiryFormControlFactory $carInquiryFormControlFactory,
    ) {
    }

    public function loadState(array $params): void
    {
        foreach (['priceFrom', 'priceTo', 'yearFrom', 'yearTo', 'kmFrom', 'kmTo'] as $key) {
            if (isset($params[$key]) && $params[$key] === '') {
                $params[$key] = null;
            }
        }
        parent::loadState($params);
    }

    public function beforeRender(): void
    {
        parent::beforeRender();
        $this->lang = $this->getParameter('lang') ?? 'cs';
        $this->template->lang = $this->lang;
        $this->template->lightNav = false;
        $this->template->homepage = false;
    }

    public function renderList(): void
    {
        $filters = [
            'znacka'    => $this->znacka,
            'karoserie' => $this->karoserie,
            'palivo'    => $this->palivo,
            'stitek'    => $this->stitek,
            'odpocet'   => $this->odpocet,
            'yearFrom'  => $this->yearFrom,
            'yearTo'    => $this->yearTo,
            'kmFrom'    => $this->kmFrom,
            'kmTo'      => $this->kmTo,
            'priceFrom' => $this->priceFrom,
            'priceTo'   => $this->priceTo,
        ];

        $filtered = $this->orm->cars->findFiltered($filters);

        $this->numOfPages = max(1, (int) ceil(count($filtered) / $this->limit));

        $this->template->cars = $filtered
            ->limitBy($this->limit, ($this->page - 1) * $this->limit)
            ->orderBy(['createdAt' => $this->order]);

        $this->template->order = $this->order;
        $this->template->numOfPages = $this->numOfPages;
        $this->template->page = $this->page;
        $this->template->totalCount = count($filtered);

        $this->template->brands    = $this->orm->cars->getDistinctValues('znacka');
        $this->template->bodyworks = $this->orm->cars->getDistinctValues('karoserie');
        $this->template->fuels     = $this->orm->cars->getDistinctValues('palivo');
        $this->template->stitky    = $this->orm->cars->getDistinctValues('stitek');

        $this->template->filterZnacka    = $this->znacka;
        $this->template->filterKaroserie = $this->karoserie;
        $this->template->filterPalivo    = $this->palivo;
        $this->template->filterStitek    = $this->stitek;
        $this->template->filterPriceFrom = $this->priceFrom;
        $this->template->filterPriceTo   = $this->priceTo;
        $this->template->filterYearFrom  = $this->yearFrom;
        $this->template->filterYearTo    = $this->yearTo;
        $this->template->filterKmFrom    = $this->kmFrom;
        $this->template->filterKmTo      = $this->kmTo;
        $this->template->filterOdpocet   = $this->odpocet;
    }

    public function renderDetail(string $detailUrl): void
    {
        $car = $this->orm->cars->findByDetailUrl($detailUrl);
        $cars = $this->orm->cars->findRandom(4);

        if ($car === null) {
            $this->error('Car not found', 404);
        }

        $this->currentCar = $car;
        $this->template->car = $car;
        $this->template->cars = $cars;
    }

    protected function createComponentCarInquiryForm(): CarInquiryFormControl
    {
        $control = $this->carInquiryFormControlFactory->create($this->getParameter('lang') ?? 'cs');
        if ($this->currentCar !== null) {
            $control->setCarInfo(
                trim($this->currentCar->znacka . ' ' . $this->currentCar->model),
                $this->currentCar->externalId,
            );
        }
        return $control;
    }

    public function formatTemplateFiles(): array
    {
        $name = $this->getAction();
        return [__DIR__ . "/templates/$name.latte"];
    }

    public function handleOrder($direction)
    {
        $this->order = $direction;
        $this->redrawControl('filter');
    }

    public function handleShowPage(int $page)
    {
        $this->page = $page;
        $this->redrawControl('filter');
    }
}