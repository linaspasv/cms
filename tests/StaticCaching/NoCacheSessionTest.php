<?php

namespace Tests\StaticCaching;

use Illuminate\Support\Facades\Cache;
use Mockery;
use Statamic\StaticCaching\NoCache\Session;
use Statamic\StaticCaching\NoCache\StringFragment;
use Statamic\StaticCaching\NoCache\StringRegion;
use Tests\FakesContent;
use Tests\FakesViews;
use Tests\PreventSavingStacheItemsToDisk;
use Tests\TestCase;

class NoCacheSessionTest extends TestCase
{
    use FakesViews;
    use FakesContent;
    use PreventSavingStacheItemsToDisk;

    /** @test */
    public function when_pushing_a_region_it_will_filter_out_cascade()
    {
        $session = new Session('/');

        $session->setCascade([
            'csrf_token' => 'abc',
            'now' => 'carbon',
            'title' => 'base title',
        ]);

        $session->pushRegion('', [
            'csrf_token' => 'abc',
            'now' => 'carbon',
            'title' => 'different title',
            'foo' => 'bar',
            'baz' => 'qux',
        ], '');

        $this->assertEquals([
            'title' => 'different title',
            'foo' => 'bar',
            'baz' => 'qux',
        ], collect($session->getRegions())->first()->context());
    }

    /** @test */
    public function it_gets_the_fragment_data()
    {
        // fragment data should be the context,
        // with the cascade merged in.

        $session = new Session('/');

        $session->pushRegion('', [
            'foo' => 'bar',
            'baz' => 'qux',
            'title' => 'local title',
        ], '');

        $region = collect($session->getRegions())->first();

        $session->setCascade([
            'csrf_token' => 'abc',
            'now' => 'carbon',
            'title' => 'root title',
        ]);

        $this->assertEquals([
            'csrf_token' => 'abc',
            'now' => 'carbon',
            'foo' => 'bar',
            'baz' => 'qux',
            'title' => 'local title',
        ], $region->fragmentData());
    }

    /** @test */
    public function it_writes()
    {
        // Testing that the cache key used is unique to the url.
        // The contents aren't really important.

        Cache::shouldReceive('forever')
            ->with('nocache::session.'.md5('/'), Mockery::any())
            ->once();

        Cache::shouldReceive('forever')
            ->with('nocache::session.'.md5('/foo'), Mockery::any())
            ->once();

        tap(new Session('/'), function ($session) {
            $session->pushRegion('test', [], '.html');
        })->write();

        tap(new Session('/foo'), function ($session) {
            $session->pushRegion('test', [], '.html');
        })->write();
    }

    /** @test */
    public function it_restores_from_cache()
    {
        Cache::forever('nocache::session.'.md5('http://localhost/test'), [
            'regions' => [
                $regionOne = Mockery::mock(StringRegion::class),
                $regionTwo = Mockery::mock(StringRegion::class),
            ],
        ]);

        $this->createPage('/test', [
            'with' => ['title' => 'Test page'],
        ]);

        $session = new Session('http://localhost/test');
        $this->assertEquals([], $session->getRegions()->all());
        $this->assertEquals([], $session->getCascade());

        $session->restore();

        $this->assertEquals([$regionOne, $regionTwo], $session->getRegions()->all());
        $this->assertNotEquals([], $cascade = $session->getCascade());
        $this->assertEquals('/test', $cascade['url']);
        $this->assertEquals('Test page', $cascade['title']);
        $this->assertEquals('http://localhost/cp', $cascade['cp_url']);
    }

    /** @test */
    public function a_singleton_is_bound_in_the_container()
    {
        $this->get('/test?foo=bar');

        $session = $this->app->make(Session::class);

        $this->assertInstanceOf(Session::class, $session);
        $this->assertEquals('http://localhost/test?foo=bar', $session->getUrl());
    }

    /** @test */
    public function it_writes_session_if_a_nocache_tag_is_used()
    {
        $this->withStandardFakeViews();
        $this->viewShouldReturnRaw('default', '{{ title }} {{ nocache }}{{ title }}{{ /nocache }}');
        $this->createPage('test', ['with' => ['title' => 'Test']]);

        $this->assertNull(Cache::get('nocache::session.'.md5('http://localhost/test')));

        $this
            ->get('/test')
            ->assertOk()
            ->assertSee('Test Test');

        $this->assertNotNull(Cache::get('nocache::session.'.md5('http://localhost/test')));
    }

    /** @test */
    public function it_doesnt_write_session_if_a_nocache_tag_is_not_used()
    {
        $this->withStandardFakeViews();
        $this->viewShouldReturnRaw('default', '{{ title }}');
        $this->createPage('test', ['with' => ['title' => 'Test']]);

        $this->assertNull(Cache::get('nocache::session.'.md5('http://localhost/test')));

        $this
            ->get('/test')
            ->assertOk()
            ->assertSee('Test');

        $this->assertNull(Cache::get('nocache::session.'.md5('http://localhost/test')));
    }

    /** @test */
    public function it_restores_session_if_theres_a_nocache_placeholder_in_the_response()
    {
        $this->withStandardFakeViews();
        $this->viewShouldReturnRendered('default', 'Hello <span class="nocache" data-nocache="abc">NOCACHE_PLACEHOLDER</span>');
        $this->createPage('test');

        Cache::put('nocache::session.'.md5('http://localhost/test'), [
            'regions' => [
                'abc' => $region = Mockery::mock(StringRegion::class),
            ],
        ]);

        $fragment = Mockery::mock(StringFragment::class);
        $fragment->shouldReceive('render')->andReturn('world');
        $region->shouldReceive('fragment')->andReturn($fragment);

        $this
            ->get('/test')
            ->assertOk()
            ->assertSee('Hello world');

        $this->assertEquals(['abc' => $region], app(Session::class)->getRegions()->all());
    }

    /** @test */
    public function it_doesnt_restore_session_if_there_is_no_nocache_placeholder_in_the_response()
    {
        $this->withStandardFakeViews();
        $this->viewShouldReturnRendered('default', 'Hello');
        $this->createPage('test');

        Cache::put('nocache::session.'.md5('http://localhost/test'), [
            'regions' => ['abc' => ['type' => 'string', 'contents' => 'world', 'extension' => 'html', 'context' => ['foo' => 'bar']]],
        ]);

        $this
            ->get('/test')
            ->assertOk()
            ->assertSee('Hello');

        $this->assertEquals([], app(Session::class)->getRegions()->all());
    }
}