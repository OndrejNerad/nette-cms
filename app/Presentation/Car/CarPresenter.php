<?php declare(strict_types=1);

namespace App\Presentation\Car;

use App\Model\CarEquipment\CarEquipmentRepository;
use App\Model\Orm;
use Nette\Application\UI\Presenter;

class CarPresenter extends Presenter
{
    private string $lang;

    public $order = 'DESC';

    public $page = 1;
    public $numOfPages = 1;

    public $limit = 6;

    public function __construct(
        private readonly Orm $orm,
        private readonly CarEquipmentRepository $carEquipmentRepository,
    ) {
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
//        $allCars = $this->orm->cars->findAll();
//
//        if (count($allCars) % $this->limit === 0) {
//            $this->numOfPages = count($allCars) / $this->limit;
//        } else {
//            $this->numOfPages = (int) (count($allCars) / $this->limit) + 1;
//        }
//
//        $this->template->cars = $this->orm->cars->findAll()->limitBy($this->limit, ($this->page - 1) * $this->limit)->orderBy(['createdAt' => $this->order]);
//        $this->template->order = $this->order;
//        $this->template->numOfPages = $this->numOfPages;
//        $this->template->page = $this->page;

        $allCars = $this->orm->cars->findAll();

        $this->numOfPages = max(1, (int) ceil(count($allCars) / $this->limit));

        $this->template->cars = $this->orm->cars->findAll()
            ->limitBy($this->limit, ($this->page - 1) * $this->limit)
            ->orderBy(['createdAt' => $this->order]);

        $this->template->order = $this->order;
        $this->template->numOfPages = $this->numOfPages;
        $this->template->page = $this->page;
    }

    public function renderDetail(string $detailUrl): void
    {
        $car = $this->orm->cars->findByDetailUrl($detailUrl);
        $cars = $this->orm->cars->findAll()->limitBy(4);

        if ($car === null) {
            $this->error('Car not found', 404);
        }

        $this->template->car = $car;
        $this->template->cars = $cars;
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