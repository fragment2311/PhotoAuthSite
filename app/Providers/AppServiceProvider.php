<?php

namespace App\Providers;

use App\Models\Camera;
use App\Models\ControllerDevice;
use App\Models\Location;
use App\Models\PairDevice;
use App\Models\Webuser;
use App\Observers\ModelChangeHistoryObserver;
use App\Services\AppSettingsFileStore;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Camera::observe(ModelChangeHistoryObserver::class);
        ControllerDevice::observe(ModelChangeHistoryObserver::class);
        Location::observe(ModelChangeHistoryObserver::class);
        PairDevice::observe(ModelChangeHistoryObserver::class);
        Webuser::observe(ModelChangeHistoryObserver::class);

        try {
            $settings = app(AppSettingsFileStore::class)->loadDecrypted();

            $pick = static function (string $key, mixed $fallback = null) use ($settings): mixed {
                return array_key_exists($key, $settings) ? $settings[$key] : $fallback;
            };

            $galleryRootDir = trim((string) $pick('gallery_root_dir', config('app.gallery_root_dir', config('app.gallery_root_dir_default', ''))), '/');
            if ($galleryRootDir === '') {
                $galleryRootDir = trim((string) config('app.gallery_root_dir_default', ''), '/');
            }

            $photosStaffRaw = trim((string) $pick('photos_staff_path', config('app.photos_staff_path', config('app.photos_staff_path_default', ''))), '/');
            if ($photosStaffRaw === '') {
                $photosStaffRaw = trim((string) config('app.photos_staff_path_default', ''), '/');
            }
            $photosStaffPath = $photosStaffRaw !== '' ? ('/' . $photosStaffRaw) : '';
            $photosNotFoundPath = trim((string) $pick('photos_not_found_path', config('app.photos_not_found_path', '')));
            $photosHttpScheme = trim((string) $pick('photos_http_scheme', config('app.photos_http_scheme', '')));
            $licenseClientId = $pick('license_client_id', config('license.client_id'));

            if (is_string($licenseClientId)) {
                $licenseClientId = trim($licenseClientId);
                if ($licenseClientId === '') {
                    $licenseClientId = null;
                }
            }

            if (!in_array($photosHttpScheme, ['http', 'https'], true)) {
                $photosHttpScheme = '';
            }

            config([
                'app.gallery_root_dir' => $galleryRootDir,
                'app.photos_staff_path' => $photosStaffPath,
                'app.photos_not_found_path' => $photosNotFoundPath,
                'app.photos_http_scheme' => $photosHttpScheme,
                'license.client_id' => $licenseClientId,

                'database.connections.remote_mysql.host' => (string) $pick('db_host', config('database.connections.remote_mysql.host')),
                'database.connections.remote_mysql.port' => (string) $pick('db_port', config('database.connections.remote_mysql.port')),
                'database.connections.remote_mysql.database' => (string) $pick('db_name', config('database.connections.remote_mysql.database')),
                'database.connections.remote_mysql.username' => (string) $pick('db_user', config('database.connections.remote_mysql.username')),
                'database.connections.remote_mysql.password' => (string) $pick('db_pass', config('database.connections.remote_mysql.password')),
            ]);

            $ftpHost = (string) $pick('photo_ftps_host', config('ftp.host', ''));
            $ftpPort = (int) $pick('photo_ftps_port', config('ftp.port'));
            $ftpRoot = (string) $pick('photo_ftps_dir', config('ftp.root'));
            $ftpUser = (string) $pick('photo_ftps_user', config('ftp.username', ''));
            $ftpPass = (string) $pick('photo_ftps_pass', config('ftp.password', ''));
            $ftpTimeout = max(1, (int) config('ftp.timeout', 30));
            $defaultFtpPort = max(1, (int) config('ftp.port', 21));

            if ($ftpPort <= 0) {
                $ftpPort = $defaultFtpPort;
            }
            if (trim($ftpRoot) === '') {
                $ftpRoot = (string) config('ftp.root', '/');
            }
            if (trim($ftpRoot) === '') {
                $ftpRoot = '/';
            }

            config([
                'filesystems.disks.ftp' => [
                    'driver' => 'ftp',
                    'host' => $ftpHost,
                    'username' => $ftpUser,
                    'password' => $ftpPass,
                    'port' => $ftpPort,
                    'root' => $ftpRoot,
                    'passive' => (int) $pick('photo_ftps_passive', config('ftp.passive', true) ? 1 : 0) === 1,
                    'ssl' => (int) $pick('photo_ftps_ssl', config('ftp.ssl', true) ? 1 : 0) === 1,
                    'timeout' => $ftpTimeout,
                    'ignorePassiveAddress' => true,
                    'throw' => false,
                ],
            ]);
        } catch (\Throwable $e) {
            // Нельзя ронять приложение из-за некорректного settings.json.
        }
    }
}
