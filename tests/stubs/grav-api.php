<?php

declare(strict_types=1);

/**
 * Minimal stubs so PHPStan can analyze Grav 2.0 API integration without grav-plugin-api installed.
 */

namespace Grav\Plugin\Api\Controllers {
    abstract class AbstractApiController
    {
        /** @var \Grav\Common\Grav */
        protected $grav;

        /** @var \Grav\Common\Config\Config */
        protected $config;

        protected function getUser(mixed $request): mixed
        {
            return null;
        }

        protected function isSuperAdmin(mixed $user): bool
        {
            return false;
        }

        protected function hasPermission(mixed $user, string $permission): bool
        {
            return false;
        }

        protected function requirePermission(mixed $request, string $permission): void
        {
        }

        /** @return array<string, mixed> */
        protected function getRequestBody(mixed $request): array
        {
            return [];
        }
    }
}

namespace Grav\Plugin\Api\Response {
    final class ApiResponse
    {
        public static function create(mixed $data): mixed
        {
            return $data;
        }
    }
}

namespace Grav\Plugin\Api\Exceptions {
    class ForbiddenException extends \RuntimeException
    {
    }

    class ValidationException extends \RuntimeException
    {
    }
}

namespace Grav\Common {
    class Plugins
    {
        public static function getPlugin(string $name): mixed
        {
            return null;
        }
    }
}
