<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../router.php';

class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    public function testSimpleGetRoute(): void
    {
        $called = false;
        $this->router->get('/test', function() use (&$called) {
            $called = true;
            return 'matched';
        });

        $result = $this->router->dispatch('/test', 'GET');
        $this->assertTrue($called);
        $this->assertEquals('matched', $result);
    }

    public function testRouteWithParameter(): void
    {
        $this->router->get('/users/:id', function($id) {
            return "User ID: {$id}";
        });

        $result = $this->router->dispatch('/users/123', 'GET');
        $this->assertEquals('User ID: 123', $result);
    }

    public function testRouteWithMultipleParameters(): void
    {
        $this->router->get('/posts/:post_id/comments/:comment_id', function($postId, $commentId) {
            return "Post: {$postId}, Comment: {$commentId}";
        });

        $result = $this->router->dispatch('/posts/42/comments/99', 'GET');
        $this->assertEquals('Post: 42, Comment: 99', $result);
    }

    public function testMethodMismatchReturnsNull(): void
    {
        $this->router->get('/test', function() {
            return 'get';
        });

        $result = $this->router->dispatch('/test', 'POST');
        $this->assertNull($result);
    }

    public function testNonMatchingRouteReturnsNull(): void
    {
        $this->router->get('/test', function() {
            return 'matched';
        });

        $result = $this->router->dispatch('/other', 'GET');
        $this->assertNull($result);
    }

    public function testPostRoute(): void
    {
        $called = false;
        $this->router->post('/submit', function() use (&$called) {
            $called = true;
            return 'posted';
        });

        $result = $this->router->dispatch('/submit', 'POST');
        $this->assertTrue($called);
        $this->assertEquals('posted', $result);
    }

    public function testDeleteRoute(): void
    {
        $called = false;
        $this->router->delete('/items/:id', function($id) use (&$called) {
            $called = true;
            return "deleted: {$id}";
        });

        $result = $this->router->dispatch('/items/5', 'DELETE');
        $this->assertTrue($called);
        $this->assertEquals('deleted: 5', $result);
    }

    public function testTrailingSlashIsRemoved(): void
    {
        $this->router->get('/test', function() {
            return 'matched';
        });

        $result = $this->router->dispatch('/test/', 'GET');
        $this->assertEquals('matched', $result);
    }

    public function testQueryStringIsIgnored(): void
    {
        $this->router->get('/test', function() {
            return 'matched';
        });

        $result = $this->router->dispatch('/test?foo=bar&baz=qux', 'GET');
        $this->assertEquals('matched', $result);
    }

    public function testRootRoute(): void
    {
        $this->router->get('/', function() {
            return 'home';
        });

        $result = $this->router->dispatch('/', 'GET');
        $this->assertEquals('home', $result);
    }
}
