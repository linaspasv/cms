<?php

namespace Statamic\Http\Controllers\CP\Preferences\Nav;

use Illuminate\Http\Request;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Preference;
use Statamic\Facades\User;
use Statamic\Http\Controllers\Controller;

class DefaultNavController extends Controller
{
    use Concerns\HasNavBuilder;

    public function edit()
    {
        abort_unless(User::current()->isSuper(), 403);

        $preferences = Preference::default()->get('nav');

        $nav = $preferences
            ? Nav::build($preferences)
            : Nav::buildWithoutPreferences();

        return $this->navBuilder($nav, [
            'title' => 'Global Default Nav',
            'updateUrl' => cp_route('preferences.nav.default.update'),
            'destroyUrl' => cp_route('preferences.nav.default.destroy'),
        ]);
    }

    public function update(Request $request)
    {
        abort_unless(User::current()->isSuper(), 403);

        $nav = $this->getUpdatedNav($request);

        Preference::default()->set('nav', $nav)->save();

        return true;
    }

    public function destroy()
    {
        abort_unless(User::current()->isSuper(), 403);

        Preference::default()->remove('nav')->save();

        return true;
    }
}
