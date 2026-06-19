<?php declare(strict_types=1);

namespace App\Security;

use App\Repository\OperatorRepository;
use Nette\Security\AuthenticationException;
use Nette\Security\Authenticator;
use Nette\Security\Passwords;
use Nette\Security\SimpleIdentity;

/**
 * Authenticates operators against the `operators` table.
 * Implements Nette\Security\Authenticator so it integrates
 * with $this->getUser()->login($email, $password) in presenters.
 */
final class OperatorAuthenticator implements Authenticator
{
    public function __construct(
        private readonly OperatorRepository $operatorRepository,
        private readonly Passwords          $passwords,
    ) {}

    /**
     * @param  string[] $credentials  [email, password]
     * @throws AuthenticationException
     */
    public function authenticate(string $username, string $password): SimpleIdentity
    {
        $operator = $this->operatorRepository->findByEmail($username);

        if ($operator === null) {
            throw new AuthenticationException('Invalid email or password.', self::IDENTITY_NOT_FOUND);
        }

        if (!$this->passwords->verify($password, $operator->password_hash)) {
            throw new AuthenticationException('Invalid email or password.', self::INVALID_CREDENTIAL);
        }

        // Rehash if bcrypt cost changed
        if ($this->passwords->needsRehash($operator->password_hash)) {
            $operator->update(['password_hash' => $this->passwords->hash($password)]);
        }

        return new SimpleIdentity(
            $operator->id,
            $operator->role,
            [
                'name'  => $operator->name,
                'email' => $operator->email,
            ],
        );
    }
}
