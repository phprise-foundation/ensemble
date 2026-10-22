<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Application\Tenancy\Install;
use App\Domain\Security\PlainPassword;
use App\Domain\Tenancy\Login;
use App\Domain\Tenancy\WorkspacePrefix;
use App\Infrastructure\Authentication\ArgonPasswordHasher;
use App\Infrastructure\Bootstrap\TenancyBootstrap;
use App\Infrastructure\Persistence\Doctrine\DoctrineEntityManagerFactory;
use App\Infrastructure\Persistence\Doctrine\Mapper\MembershipRecordMapper;
use App\Infrastructure\Persistence\Doctrine\Mapper\UserRecordMapper;
use App\Infrastructure\Persistence\Doctrine\Mapper\WorkspaceRecordMapper;
use App\Infrastructure\Persistence\Doctrine\Repository\DoctrineMembershipRepository;
use App\Infrastructure\Persistence\Doctrine\Repository\DoctrineUserRepository;
use App\Infrastructure\Persistence\Doctrine\Repository\DoctrineWorkspaceRepository;
use App\Interface\Http\JsonResponder;
use App\Interface\Http\Tenancy\TenancyRouter;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

final class TenancyTestApp
{
    private TenancyRouter $router;

    public function __construct(
        string $adminLogin = 'admin',
        string $adminPassword = 'admin1234',
        string $workspaceName = 'Default',
        string $workspacePrefix = 'KAN',
    ) {
        $factory = DoctrineEntityManagerFactory::fromEnvironment(true);
        $entityManager = $factory->recreateSchema();
        $users = new DoctrineUserRepository($entityManager, new UserRecordMapper());
        $workspaces = new DoctrineWorkspaceRepository($entityManager, new WorkspaceRecordMapper());
        $memberships = new DoctrineMembershipRepository($entityManager, new MembershipRecordMapper());
        $install = new Install($users, $workspaces, $memberships, new ArgonPasswordHasher());
        $install->execute(
            new Login($adminLogin),
            new PlainPassword($adminPassword),
            $workspaceName,
            new WorkspacePrefix($workspacePrefix),
        );
        $this->router = (new TenancyBootstrap($factory, new JsonResponder()))->router();
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->router->handle($request);
    }

    public function login(string $login, string $password): string
    {
        $response = $this->handle($this->request('POST', '/api/auth/login', [
            'login' => $login,
            'password' => $password,
        ]));
        /** @var array<array-key, mixed> $data */
        $data = json_decode((string) $response->getBody(), true);
        Assert::assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $token = $data['token'] ?? null;
        if (!is_string($token)) {
            throw new RuntimeException('Login response has no token.');
        }

        return $token;
    }

    /** @param array<array-key, mixed> $body */
    public function request(string $method, string $path, array $body = [], ?string $token = null): ServerRequestInterface
    {
        $headers = ['Content-Type' => 'application/json'];
        if ($token !== null) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return new ServerRequest($method, $path, $headers, (string) json_encode($body));
    }

    /** @return array<array-key, mixed> */
    public function json(ResponseInterface $response, int $expected = 200): array
    {
        Assert::assertSame($expected, $response->getStatusCode(), (string) $response->getBody());
        /** @var mixed $data */
        $data = json_decode((string) $response->getBody(), true);
        if (!is_array($data)) {
            throw new RuntimeException('Response is not a JSON object.');
        }

        return $data;
    }
}
