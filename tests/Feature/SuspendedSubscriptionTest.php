<?php

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Models\Movement;
use App\Models\Party;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->company = Company::factory()->suspended()->create(['name' => 'شركة النور', 'primary_color' => '#0F766E']);
    $this->owner = User::factory()->for($this->company)->create();
    $this->warehouse = User::factory()->warehouse()->for($this->company)->create();
});

// --- Every route is closed except the suspended page and logout (FR-03) --------------------------

/**
 * Real records for the route parameters, so a bound route (`/products/{product}`) resolves instead of answering 404.
 */
function routeParameterValues(): array
{
    $company = test()->company;

    return [
        'product' => Product::factory()->for($company)->create()->id,
        'party' => Party::factory()->for($company)->create()->id,
        'movement' => Movement::factory()->for($company)->create(['warehouse_id' => test()->warehouse->id, 'created_by' => test()->warehouse->id])->id,
        'warehouse' => test()->warehouse->id,
        'company' => $company->id,
    ];
}

function signedInRoutes(): array
{
    $routes = [];

    foreach (Route::getRoutes() as $route) {
        $middleware = $route->gatherMiddleware();

        if (! in_array('auth', $middleware, true) || in_array($route->getName(), ['logout', 'subscription.suspended'], true)) {
            continue;
        }

        $routes[] = [array_values(array_diff($route->methods(), ['HEAD']))[0], $route->uri()];
    }

    return $routes;
}

test('a suspended company reaches no signed-in route except the suspended page and logout', function (string $role) {
    $user = $role === 'owner' ? $this->owner : $this->warehouse;
    $values = routeParameterValues();
    $routes = signedInRoutes();

    expect($routes)->not->toBeEmpty();

    foreach ($routes as [$method, $uri]) {
        $path = '/'.preg_replace_callback('/\{(\w+)\??\}/', fn ($m) => $values[$m[1]] ?? 1, $uri);

        $this->actingAs($user)->call($method, $path)
            ->assertRedirect(route('subscription.suspended'), "{$method} {$path} was not redirected to the suspended page");
    }
})->with(['owner', 'warehouse']);

test('every route is either for guests, signed in, or on a short reviewed list of public routes', function () {
    $public = [];

    foreach (Route::getRoutes() as $route) {
        $middleware = $route->gatherMiddleware();

        if (in_array('auth', $middleware, true) || in_array('guest', $middleware, true)) {
            continue;
        }

        $public[] = $route->getName() ?? $route->uri();
    }

    // `/` only redirects to the dashboard, `up` is the health check, `storage.local` serves signed temporary files.
    expect(array_values(array_diff($public, ['/', 'up', 'storage.local'])))->toBe([]);
});

test('a suspended company cannot write data either', function () {
    $this->actingAs($this->owner)->post('/products', ['name' => 'مادة', 'unit' => 'piece'])
        ->assertRedirect(route('subscription.suspended'));
    $this->post('/parties', ['name' => 'طرف', 'type' => 'customer'])
        ->assertRedirect(route('subscription.suspended'));

    expect(Product::withoutGlobalScopes()->count())->toBe(0)
        ->and(Party::withoutGlobalScopes()->count())->toBe(0);
});

// --- Sign in, the page, sign out -----------------------------------------------------------------

test('users of a suspended company sign in and land on the suspended page', function (string $role) {
    $user = $role === 'owner' ? $this->owner : $this->warehouse;

    $this->followingRedirects()
        ->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertOk()
        ->assertSee('اشتراك شركتك موقوف');

    $this->assertAuthenticatedAs($user);
})->with(['owner', 'warehouse']);

test('a deactivated account is still refused at login, whatever its company', function () {
    $inactive = User::factory()->warehouse()->inactive()->for($this->company)->create();

    $this->post('/login', ['email' => $inactive->email, 'password' => 'password'])
        ->assertSessionHasErrors(['login' => trans('auth.inactive')]);
    $this->assertGuest();
});

test('the page shows the company, its colour and a logout button, without the sidebar', function () {
    $this->actingAs($this->owner)->get(route('subscription.suspended'))
        ->assertOk()
        ->assertSee('شركة النور')
        ->assertSee('--color-primary: #0F766E', false)
        ->assertSee('تسجيل الخروج')
        ->assertSee(route('logout'), false)
        ->assertDontSee(route('products.index'))
        ->assertDontSee(route('warehouses.index'));
});

test('logging out works from the suspended page', function () {
    $this->actingAs($this->owner)->post('/logout')->assertRedirect('/');

    $this->assertGuest();
});

test('a guest is sent to login', function () {
    $this->get(route('subscription.suspended'))->assertRedirect('/login');
});

// --- Who sees the contact details ----------------------------------------------------------------

test('the owner sees how to reach the platform', function () {
    config(['wms.support' => ['phone' => '+963 11 555 1234', 'email' => 'help@example.com', 'hours' => '9 – 5']]);

    $this->actingAs($this->owner)->get(route('subscription.suspended'))
        ->assertSee('للتفعيل تواصل معنا')
        ->assertSee('+963 11 555 1234')
        ->assertSee('help@example.com')
        ->assertSee('9 – 5')
        ->assertSee('https://wa.me/963115551234', false);
});

test('a contact row that is not configured is not shown', function () {
    config(['wms.support' => ['phone' => '', 'email' => 'help@example.com', 'hours' => null]]);

    $this->actingAs($this->owner)->get(route('subscription.suspended'))
        ->assertSee('help@example.com')
        ->assertDontSee('الهاتف / واتساب')
        ->assertDontSee('ساعات الدوام')
        ->assertDontSee('wa.me');
});

test('a warehouse user is told to contact the company owner, with no platform contact details', function () {
    config(['wms.support' => ['phone' => '+963 11 555 1234', 'email' => 'help@example.com', 'hours' => '9 – 5']]);

    $this->actingAs($this->warehouse)->get(route('subscription.suspended'))
        ->assertOk()
        ->assertSee('تواصل مع صاحب الشركة')
        ->assertDontSee('+963 11 555 1234')
        ->assertDontSee('help@example.com')
        ->assertDontSee('wa.me');
});

// --- Reactivation and the platform owner ---------------------------------------------------------

test('after reactivation the page leads back to the dashboard and all the data is still there', function () {
    $product = Product::factory()->for($this->company)->create(['name' => 'مادة محفوظة']);
    $party = Party::factory()->for($this->company)->create(['name' => 'طرف محفوظ']);

    $this->company->update(['status' => CompanyStatus::Active]);

    $this->actingAs($this->owner)->get(route('subscription.suspended'))->assertRedirect(route('dashboard'));
    $this->get('/products')->assertOk()->assertSee($product->name);
    $this->get('/parties')->assertOk()->assertSee($party->name);
});

test('the platform owner is never sent to the suspended page', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->get('/admin/companies')->assertOk();
    $this->get(route('subscription.suspended'))->assertRedirect(route('dashboard'));
});

test('an expired trial alone does not send anyone to the suspended page', function () {
    $owner = User::factory()->for(Company::factory()->trialExpired())->create();

    $this->actingAs($owner)->get('/dashboard')->assertOk();
});
