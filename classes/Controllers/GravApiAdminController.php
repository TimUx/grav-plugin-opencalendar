<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Controllers;

use Grav\Common\Grav;
use Grav\Common\Plugins;
use Grav\Plugin\Api\Controllers\AbstractApiController;
use Grav\Plugin\Api\Exceptions\ForbiddenException;
use Grav\Plugin\Api\Exceptions\ValidationException;
use Grav\Plugin\Api\Response\ApiResponse;
use Grav\Plugin\OpenCalendarPlugin;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Admin Next (Grav 2.0 API plugin) endpoints for sync / status / upload.
 *
 * Only loaded when the Grav API plugin fires onApiRegisterRoutes — safe on Grav 1.7.
 */
class GravApiAdminController extends AbstractApiController
{
    public function status(ServerRequestInterface $request): ResponseInterface
    {
        $this->requirePluginAdmin($request);

        return ApiResponse::create($this->admin()->status());
    }

    public function sync(ServerRequestInterface $request): ResponseInterface
    {
        $this->requirePluginAdmin($request);

        @set_time_limit(0);
        @session_write_close();

        $query = $request->getQueryParams();
        $source = isset($query['source']) ? (string) $query['source'] : null;
        $body = $this->getRequestBody($request);
        if (($source === null || $source === '') && is_array($body) && isset($body['source'])) {
            $source = (string) $body['source'];
        }

        return ApiResponse::create($this->admin(true)->syncNow($source !== '' ? $source : null));
    }

    public function rebuild(ServerRequestInterface $request): ResponseInterface
    {
        $this->requirePluginAdmin($request);

        @set_time_limit(0);
        @session_write_close();

        return ApiResponse::create($this->admin(true)->rebuildDatabase());
    }

    public function clearCache(ServerRequestInterface $request): ResponseInterface
    {
        $this->requirePluginAdmin($request);

        return ApiResponse::create($this->admin(true)->clearCache());
    }

    public function upload(ServerRequestInterface $request): ResponseInterface
    {
        $this->requirePluginAdmin($request);

        @set_time_limit(0);
        @session_write_close();

        $files = $request->getUploadedFiles();
        $uploaded = $files['calendar'] ?? null;
        if (!$uploaded instanceof UploadedFileInterface) {
            throw new ValidationException('No calendar file was selected.');
        }

        if ($uploaded->getError() !== UPLOAD_ERR_OK) {
            throw new ValidationException('Upload failed with error code ' . $uploaded->getError() . '.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'oc-upload-');
        if ($tmp === false) {
            throw new ValidationException('Unable to create a temporary upload file.');
        }

        try {
            $uploaded->moveTo($tmp);
            $body = $this->getRequestBody($request);
            $name = is_array($body) ? trim((string) ($body['name'] ?? '')) : '';
            if ($name === '') {
                $parsed = $request->getParsedBody();
                if (is_array($parsed)) {
                    $name = trim((string) ($parsed['name'] ?? ''));
                }
            }

            $result = $this->admin(true)->uploadCalendar([
                'name' => (string) ($uploaded->getClientFilename() ?: 'calendar.ics'),
                'type' => (string) ($uploaded->getClientMediaType() ?: 'application/octet-stream'),
                'tmp_name' => $tmp,
                'error' => UPLOAD_ERR_OK,
                'size' => (int) ($uploaded->getSize() ?: filesize($tmp) ?: 0),
            ], $name, true);
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }

        if (($result['ok'] ?? false) !== true) {
            throw new ValidationException((string) ($result['message'] ?? 'Upload failed.'));
        }

        return ApiResponse::create($result);
    }

    private function requirePluginAdmin(ServerRequestInterface $request): void
    {
        $user = $this->getUser($request);

        if (method_exists($this, 'isSuperAdmin') && $this->isSuperAdmin($user)) {
            return;
        }

        if (method_exists($this, 'hasPermission')) {
            foreach (['admin.super', 'admin.configuration', 'admin.login', 'api.access'] as $perm) {
                if ($this->hasPermission($user, $perm)) {
                    return;
                }
            }
        }

        if (method_exists($this, 'requirePermission')) {
            try {
                $this->requirePermission($request, 'api.access');

                return;
            } catch (\Throwable) {
                // fall through
            }
        }

        throw new ForbiddenException('OpenCalendar admin API requires an authenticated administrator.');
    }

    private function admin(bool $fresh = false): AdminController
    {
        $plugin = Plugins::getPlugin('opencalendar');
        if (!$plugin instanceof OpenCalendarPlugin) {
            // Fallback: plugin object via Grav plugins collection.
            $grav = Grav::instance();
            $plugins = $grav['plugins'] ?? null;
            if (is_object($plugins) && method_exists($plugins, 'get')) {
                $candidate = $plugins->get('opencalendar');
                if ($candidate instanceof OpenCalendarPlugin) {
                    $plugin = $candidate;
                }
            }
        }

        if (!$plugin instanceof OpenCalendarPlugin) {
            throw new ValidationException('OpenCalendar plugin is not loaded.');
        }

        return $plugin->createAdminController($fresh);
    }
}
