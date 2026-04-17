<?php

use PHPUnit\Framework\TestCase;

/**
 * Exercises UserRepository against an in-memory SQLite PDO. This does not
 * prove the production MySQL queries verbatim, but validates the query
 * shapes, parameter binding, and deterministic result handling — which is
 * where bugs typically hide in newly extracted repositories.
 */
final class UserRepositoryTest extends TestCase
{
    private PDO $pdo;
    private UserRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('
            CREATE TABLE users (
                id INTEGER PRIMARY KEY,
                username TEXT NOT NULL,
                full_name TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT "viewer",
                status TEXT NOT NULL DEFAULT "active"
            )
        ');
        $insert = $this->pdo->prepare(
            'INSERT INTO users (id, username, full_name, role, status) VALUES (?, ?, ?, ?, ?)'
        );
        $insert->execute([1, 'alice',   'Alice A',  'admin',  'active']);
        $insert->execute([2, 'bob',     'Bob B',    'viewer', 'active']);
        $insert->execute([3, 'carol',   'Carol C',  'viewer', 'inactive']);
        $insert->execute([4, 'dan',     'Dan D',    'admin',  'active']);

        $this->repo = new UserRepository($this->pdo);
    }

    public function testFindByIdReturnsRow(): void
    {
        $row = $this->repo->findById(1);
        $this->assertNotNull($row);
        $this->assertSame('alice', $row['username']);
    }

    public function testFindByIdReturnsNullForMissing(): void
    {
        $this->assertNull($this->repo->findById(999));
    }

    public function testFindByUsernameReturnsRow(): void
    {
        $row = $this->repo->findByUsername('bob');
        $this->assertNotNull($row);
        $this->assertSame(2, (int) $row['id']);
    }

    public function testFindByIdsBatchesAndIndexesById(): void
    {
        $rows = $this->repo->findByIds([1, 2, 999]);
        $this->assertCount(2, $rows);
        $this->assertArrayHasKey(1, $rows);
        $this->assertArrayHasKey(2, $rows);
        $this->assertSame('alice', $rows[1]['username']);
    }

    public function testFindByIdsWithEmptyArrayReturnsEmpty(): void
    {
        $this->assertSame([], $this->repo->findByIds([]));
    }

    public function testGetActiveExcludesInactive(): void
    {
        $rows = $this->repo->getActive();
        $usernames = array_column($rows, 'username');
        $this->assertContains('alice', $usernames);
        $this->assertNotContains('carol', $usernames);
    }

    public function testGetActiveByRoleFiltersCorrectly(): void
    {
        $admins = $this->repo->getActiveByRole('admin');
        $this->assertCount(2, $admins);
        foreach ($admins as $row) {
            $this->assertSame('admin', $row['role']);
            $this->assertSame('active', $row['status']);
        }
    }
}
