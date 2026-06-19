<?php declare(strict_types=1);

namespace App\Presentation\Auth;

use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Nette\Security\AuthenticationException;

final class AuthPresenter extends Presenter
{
    /** @persistent */
    public string $backlink = '';

    public function actionIn(): void
    {
        // Already logged in → go straight to the app
        if ($this->getUser()->isLoggedIn()) {
            $this->redirect(':Home:default');
        }
    }

    public function actionOut(): void
    {
        $this->getUser()->logout(true);
        $this->flashMessage('You have been signed out.', 'info');
        $this->redirect('Auth:in');
    }

    protected function createComponentSignInForm(): Form
    {
        $form = new Form();

        $form->addEmail('email', 'Email')
            ->setRequired('Please enter your email.');

        $form->addPassword('password', 'Password')
            ->setRequired('Please enter your password.');

        $form->addSubmit('send', 'Sign in');

        $form->onSuccess[] = $this->signInFormSucceeded(...);

        return $form;
    }

    private function signInFormSucceeded(Form $form, \stdClass $values): void
    {
        try {
            $this->getUser()->login($values->email, $values->password);

        } catch (AuthenticationException $e) {
            $form->addError('Incorrect email or password.');
            return;
        }

        $this->restoreRequest($this->backlink);
        $this->redirect(':Home:default');
    }
}
