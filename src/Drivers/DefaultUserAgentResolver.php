<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Drivers;

use GaiaTools\FulcrumSettings\Contracts\UserAgentResolver;
use Illuminate\Http\Request;

class DefaultUserAgentResolver implements UserAgentResolver
{
    public function __construct(protected ?Request $request = null)
    {
        $this->request = $request ?: request();
    }

    /**
     * @return array{
     *     device: string|null,
     *     browser: string|null,
     *     browser_version: string|null,
     *     os: string|null,
     *     os_version: string|null,
     *     is_mobile: bool,
     *     is_tablet: bool,
     *     is_desktop: bool,
     *     is_bot: bool
     * }
     */
    public function resolve(mixed $scope = null): array
    {
        $userAgent = $this->getUserAgent($scope);

        if (empty($userAgent)) {
            return $this->emptyResult();
        }

        return [
            'device' => $this->detectDevice($userAgent),
            'browser' => $this->detectBrowser($userAgent),
            'browser_version' => $this->detectBrowserVersion($userAgent),
            'os' => $this->detectOS($userAgent),
            'os_version' => $this->detectOSVersion($userAgent),
            'is_mobile' => $this->isMobile($userAgent),
            'is_tablet' => $this->isTablet($userAgent),
            'is_desktop' => $this->isDesktop($userAgent),
            'is_bot' => $this->isBot($userAgent),
        ];
    }

    protected function getUserAgent(mixed $scope): ?string
    {
        if (is_array($scope) && isset($scope['user_agent'])) {
            $userAgent = $scope['user_agent'];

            return is_string($userAgent) ? $userAgent : null;
        }

        if (is_object($scope) && isset($scope->user_agent)) {
            $userAgent = $scope->user_agent;

            return is_string($userAgent) ? $userAgent : null;
        }

        return $this->request?->userAgent();
    }

    protected function detectBrowser(string $userAgent): ?string
    {
        $userAgent = strtolower($userAgent);
        $rules = [
            ['edg', 'Edge'],
            ['opr', 'Opera'],
            ['opera', 'Opera'],
            ['chrome', 'Chrome'],
            ['firefox', 'Firefox'],
            ['safari', 'Safari'],
            ['msie', 'IE'],
            ['trident', 'IE'],
        ];

        return $this->matchFirstContains($userAgent, $rules, 'Unknown');
    }

    protected function detectBrowserVersion(string $userAgent): ?string
    {
        $browser = $this->detectBrowser($userAgent);
        if (! is_string($browser)) {
            return null;
        }

        $patterns = [
            'Chrome' => '/Chrome\/([0-9.]+)/i',
            'Firefox' => '/Firefox\/([0-9.]+)/i',
            'Safari' => '/Version\/([0-9.]+)/i',
            'Edge' => '/Edg\/([0-9.]+)/i',
            'Opera' => '/(Opera|OPR)\/([0-9.]+)/i',
            'IE' => '/(MSIE |rv:)([0-9.]+)/i',
        ];

        if (isset($patterns[$browser]) && preg_match($patterns[$browser], $userAgent, $matches)) {
            return end($matches);
        }

        return null;
    }

    protected function detectOS(string $userAgent): ?string
    {
        $userAgent = strtolower($userAgent);
        $rules = [
            ['windows', 'Windows'],
            ['android', 'Android'],
            ['iphone', 'iOS'],
            ['ipad', 'iOS'],
            ['ipod', 'iOS'],
            ['mac os', 'macOS'],
            ['macintosh', 'macOS'],
            ['linux', 'Linux'],
        ];

        return $this->matchFirstContains($userAgent, $rules, 'Unknown');
    }

    protected function detectOSVersion(string $userAgent): ?string
    {
        $rules = [
            ['/Windows NT ([0-9\.]+)/i', static fn (array $matches): string => $matches[1]],
            ['/Android ([0-9\.]+)/i', static fn (array $matches): string => $matches[1]],
            ['/OS ([0-9_]+)/i', static fn (array $matches): string => str_replace('_', '.', $matches[1])],
            ['/Mac OS X ([0-9_]+)/i', static fn (array $matches): string => str_replace('_', '.', $matches[1])],
        ];

        return $this->matchFirstPattern($userAgent, $rules);
    }

    protected function detectDevice(string $userAgent): ?string
    {
        return match (true) {
            $this->isTablet($userAgent) => 'Tablet',
            $this->isMobile($userAgent) => 'Mobile',
            $this->isBot($userAgent) => 'Bot',
            default => 'Desktop',
        };
    }

    protected function isMobile(string $userAgent): bool
    {
        return (bool) preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series([46])0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $userAgent);
    }

    protected function isTablet(string $userAgent): bool
    {
        return (bool) preg_match('/android|ipad|playbook|silk/i', $userAgent) && ! str_contains(strtolower($userAgent), 'mobile');
    }

    protected function isDesktop(string $userAgent): bool
    {
        return ! $this->isMobile($userAgent) && ! $this->isTablet($userAgent) && ! $this->isBot($userAgent);
    }

    protected function isBot(string $userAgent): bool
    {
        return (bool) preg_match('/bot|googlebot|crawler|spider|robot|crawling/i', $userAgent);
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $rules
     */
    protected function matchFirstContains(string $userAgent, array $rules, string $default): string
    {
        $result = $default;

        foreach ($rules as [$needle, $label]) {
            if (str_contains($userAgent, $needle)) {
                $result = $label;
                break;
            }
        }

        return $result;
    }

    /**
     * @param  array<int, array{0: string, 1: callable}>  $rules
     */
    protected function matchFirstPattern(string $userAgent, array $rules): ?string
    {
        $result = null;

        foreach ($rules as [$pattern, $formatter]) {
            $matches = [];
            if (preg_match($pattern, $userAgent, $matches)) {
                $result = $formatter($matches);
                break;
            }
        }

        return $result;
    }

    /**
     * @return array{
     *     device: string|null,
     *     browser: string|null,
     *     browser_version: string|null,
     *     os: string|null,
     *     os_version: string|null,
     *     is_mobile: bool,
     *     is_tablet: bool,
     *     is_desktop: bool,
     *     is_bot: bool
     * }
     */
    protected function emptyResult(): array
    {
        return [
            'device' => null,
            'browser' => null,
            'browser_version' => null,
            'os' => null,
            'os_version' => null,
            'is_mobile' => false,
            'is_tablet' => false,
            'is_desktop' => false,
            'is_bot' => false,
        ];
    }
}
