<?php declare(strict_types=1);

namespace App\Presentation\Home;

use Nette;


final class HomePresenter extends Nette\Application\UI\Presenter
{
    protected function startup()
    {
        parent::startup();
        if (!$this->getUser()->isLoggedIn()) {
            $this->redirect('Auth:in');
        }
    }
}
