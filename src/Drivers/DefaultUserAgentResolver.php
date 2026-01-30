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

        if (str_contains($userAgent, 'edg')) {
            return 'Edge';
        }
        if (str_contains($userAgent, 'opr') || str_contains($userAgent, 'opera')) {
            return 'Opera';
        }
        if (str_contains($userAgent, 'chrome')) {
            return 'Chrome';
        }
        if (str_contains($userAgent, 'firefox')) {
            return 'Firefox';
        }
        if (str_contains($userAgent, 'safari')) {
            return 'Safari';
        }
        if (str_contains($userAgent, 'msie') || str_contains($userAgent, 'trident')) {
            return 'IE';
        }

        return 'Unknown';
    }

    protected function detectBrowserVersion(string $userAgent): ?string
    {
        $browser = $this->detectBrowser($userAgent);
        $patterns = [
            'Chrome' => '/Chrome\/([0-9\.]+)/i',
            'Firefox' => '/Firefox\/([0-9\.]+)/i',
            'Safari' => '/Version\/([0-9\.]+)/i',
            'Edge' => '/Edg\/([0-9\.]+)/i',
            'Opera' => '/(Opera|OPR)\/([0-9\.]+)/i',
            'IE' => '/(MSIE |rv:)([0-9\.]+)/i',
        ];

        if (isset($patterns[$browser]) && preg_match($patterns[$browser], $userAgent, $matches)) {
            return end($matches);
        }

        return null;
    }

    protected function detectOS(string $userAgent): ?string
    {
        $userAgent = strtolower($userAgent);

        if (str_contains($userAgent, 'windows')) {
            return 'Windows';
        }
        if (str_contains($userAgent, 'android')) {
            return 'Android';
        }
        if (str_contains($userAgent, 'iphone') || str_contains($userAgent, 'ipad') || str_contains($userAgent, 'ipod')) {
            return 'iOS';
        }
        if (str_contains($userAgent, 'mac os') || str_contains($userAgent, 'macintosh')) {
            return 'macOS';
        }
        if (str_contains($userAgent, 'linux')) {
            return 'Linux';
        }

        return 'Unknown';
    }

    protected function detectOSVersion(string $userAgent): ?string
    {
        if (preg_match('/Windows NT ([0-9\.]+)/i', $userAgent, $matches)) {
            return $matches[1];
        }
        if (preg_match('/Android ([0-9\.]+)/i', $userAgent, $matches)) {
            return $matches[1];
        }
        if (preg_match('/OS ([0-9_]+)/i', $userAgent, $matches)) {
            return str_replace('_', '.', $matches[1]);
        }
        if (preg_match('/Mac OS X ([0-9_]+)/i', $userAgent, $matches)) {
            return str_replace('_', '.', $matches[1]);
        }

        return null;
    }

    protected function detectDevice(string $userAgent): ?string
    {
        if ($this->isTablet($userAgent)) {
            return 'Tablet';
        }
        if ($this->isMobile($userAgent)) {
            return 'Mobile';
        }
        if ($this->isBot($userAgent)) {
            return 'Bot';
        }

        return 'Desktop';
    }

    protected function isMobile(string $userAgent): bool
    {
        return (bool) preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $userAgent);
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
