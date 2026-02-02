<?php

use GaiaTools\FulcrumSettings\Drivers\DefaultUserAgentResolver;
use Illuminate\Http\Request;

test('it resolves user agent from scope array', function () {
    $resolver = new DefaultUserAgentResolver;
    $scope = ['user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'];

    $result = $resolver->resolve($scope);

    expect($result['browser'])->toBe('Chrome');
    expect($result['browser_version'])->toBe('91.0.4472.124');
    expect($result['os'])->toBe('Windows');
    expect($result['os_version'])->toBe('10.0');
    expect($result['device'])->toBe('Desktop');
    expect($result['is_desktop'])->toBeTrue();
});

test('it resolves user agent from scope object', function () {
    $resolver = new DefaultUserAgentResolver;
    $scope = (object) ['user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.1.1 Mobile/15E148 Safari/604.1'];

    $result = $resolver->resolve($scope);

    expect($result['browser'])->toBe('Safari');
    expect($result['browser_version'])->toBe('14.1.1');
    expect($result['os'])->toBe('iOS');
    expect($result['os_version'])->toBe('14.6');
    expect($result['device'])->toBe('Mobile');
    expect($result['is_mobile'])->toBeTrue();
});

test('it resolves user agent from request if scope is null', function () {
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('userAgent')->andReturn('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');

    $resolver = new DefaultUserAgentResolver($request);
    $result = $resolver->resolve();

    expect($result['browser'])->toBe('Chrome');
    expect($result['os'])->toBe('macOS');
    expect($result['os_version'])->toBe('10.15.7');
});

test('it returns empty result when no user agent is found', function () {
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('userAgent')->andReturn(null);

    $resolver = new DefaultUserAgentResolver($request);
    $result = $resolver->resolve();

    expect($result['browser'])->toBeNull();
    expect($result['os'])->toBeNull();
    expect($result['is_mobile'])->toBeFalse();
});

test('it detects various browsers', function ($userAgent, $expectedBrowser, $expectedVersion) {
    $resolver = new DefaultUserAgentResolver;
    $result = $resolver->resolve(['user_agent' => $userAgent]);

    expect($result['browser'])->toBe($expectedBrowser);
    expect($result['browser_version'])->toBe($expectedVersion);
})->with([
    ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36', 'Chrome', '91.0.4472.124'],
    ['Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0', 'Firefox', '89.0'],
    ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.1.1 Safari/605.1.15', 'Safari', '14.1.1'],
    ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36 Edg/91.0.864.59', 'Edge', '91.0.864.59'],
    ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36 OPR/77.0.4054.172', 'Opera', '77.0.4054.172'],
    ['Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)', 'IE', '10.0'],
    ['Mozilla/5.0 (Windows NT 6.1; Trident/7.0; rv:11.0) like Gecko', 'IE', '11.0'],
    ['Unknown Browser String', 'Unknown', null],
]);

test('it detects various operating systems', function ($userAgent, $expectedOS, $expectedVersion) {
    $resolver = new DefaultUserAgentResolver;
    $result = $resolver->resolve(['user_agent' => $userAgent]);

    expect($result['os'])->toBe($expectedOS);
    expect($result['os_version'])->toBe($expectedVersion);
})->with([
    ['Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'Windows', '10.0'],
    ['Mozilla/5.0 (Linux; Android 11; SM-G991B)', 'Android', '11'],
    ['Mozilla/5.0 (iPhone; CPU iPhone OS 14_6 like Mac OS X)', 'iOS', '14.6'],
    ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', 'macOS', '10.15.7'],
    ['Mozilla/5.0 (X11; Linux x86_64)', 'Linux', null],
    ['Unknown OS String', 'Unknown', null],
]);

test('it detects devices correctly', function ($userAgent, $expectedDevice) {
    $resolver = new DefaultUserAgentResolver;
    $result = $resolver->resolve(['user_agent' => $userAgent]);

    expect($result['device'])->toBe($expectedDevice);
})->with([
    ['Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'Desktop'],
    ['Mozilla/5.0 (iPhone; CPU iPhone OS 14_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.1.1 Mobile/15E148 Safari/604.1', 'Mobile'],
    ['Mozilla/5.0 (iPad; CPU OS 14_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.1.1 Safari/604.1', 'Tablet'],
    ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', 'Bot'],
]);

test('it identifies bot correctly', function () {
    $resolver = new DefaultUserAgentResolver;
    $result = $resolver->resolve(['user_agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)']);

    expect($result['is_bot'])->toBeTrue();
    expect($result['is_desktop'])->toBeFalse();
});

test('it identifies tablet correctly', function () {
    $resolver = new DefaultUserAgentResolver;
    // Android Tablet usually doesn't have "Mobile" in UA
    $result = $resolver->resolve(['user_agent' => 'Mozilla/5.0 (Linux; Android 9; SM-T860) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36']);

    expect($result['is_tablet'])->toBeTrue();
    expect($result['is_mobile'])->toBeFalse();
    expect($result['device'])->toBe('Tablet');
});
