-- PairDeviceDevices: миграция состава устройств точки.
-- Выполнять вручную в окно обслуживания, когда PhotoAuthApp и клиенты остановлены.
-- Скрипт рассчитан на фактическую структуру БД на 2026-09-23.
-- Если ранее была выполнена первая часть этого файла и в pairdevices уже нет
-- IdPairDeviceDevices, используйте pair-device-members-migration-resume.sql.
--
-- Важно: на этом MySQL @@lower_case_table_names = 1. Поэтому имя таблицы
-- физически останется pair_device_devices, даже если PHP-модель использует
-- имя PairDeviceDevices. Имена новых/переименованных столбцов — CamelCase.
--
-- Скрипт не удаляет 6 старых строк. Исходная таблица будет переименована
-- в Legacy-таблицу, а новая таблица получит целевую структуру.

-- 1. Контроль исходного состояния.
SELECT @@lower_case_table_names AS LowerCaseTableNames;
SELECT COUNT(*) AS PairDevicesCount FROM `pairdevices`;
SELECT COUNT(*) AS LegacyPairDeviceDevicesCount FROM `pair_device_devices`;

-- 2. Резервная копия. Если таблица с этим суффиксом уже существует,
-- используйте другой суффикс, а не перезаписывайте её.
CREATE TABLE `PairDevicesBackup_20260923` LIKE `pairdevices`;
INSERT INTO `PairDevicesBackup_20260923` SELECT * FROM `pairdevices`;

CREATE TABLE `PairDeviceDevicesBackup_20260923` LIKE `pair_device_devices`;
INSERT INTO `PairDeviceDevicesBackup_20260923` SELECT * FROM `pair_device_devices`;

SELECT COUNT(*) AS PairDevicesBackupCount FROM `PairDevicesBackup_20260923`;
SELECT COUNT(*) AS PairDeviceDevicesBackupCount FROM `PairDeviceDevicesBackup_20260923`;

-- 3. Сохранение устаревшей связи «точка -> одна запись контроллер+камера».
-- Сначала убирается внешний ключ, затем прежнее поле остаётся под именем
-- LegacyIdPairDeviceDevices. Это позволяет проверить и при необходимости
-- откатить миграцию без восстановления из backup.
ALTER TABLE `pairdevices`
    DROP FOREIGN KEY `fk_pairdevices_id_pair_device_devices`,
    DROP INDEX `fk_pairdevices_id_pair_device_devices`,
    CHANGE COLUMN `IdPairDeviceDevices` `LegacyIdPairDeviceDevices` BIGINT UNSIGNED NULL;

-- 4. Сохраняем старую таблицу и создаём новую таблицу состава точки.
-- На сервере с lower_case_table_names=1 физическое имя новой таблицы MySQL
-- приведёт к нижнему регистру. PHP может обращаться к ней как PairDeviceDevices.
RENAME TABLE `pair_device_devices` TO `PairDeviceDevicesLegacy_20260923`;

CREATE TABLE `PairDeviceDevices` (
    `Id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `IdPairDevice` INT NOT NULL,
    `IdController` INT NULL,
    `IdCamera` BIGINT UNSIGNED NULL,
    `Role` VARCHAR(32) NOT NULL,
    `SortOrder` SMALLINT UNSIGNED NOT NULL DEFAULT 100,
    `IsActive` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`Id`),
    UNIQUE KEY `UX_PairDeviceDevices_PairRole` (`IdPairDevice`, `Role`),
    UNIQUE KEY `UX_PairDeviceDevices_Controller` (`IdController`),
    UNIQUE KEY `UX_PairDeviceDevices_Camera` (`IdCamera`),
    KEY `IX_PairDeviceDevices_PairSort` (`IdPairDevice`, `SortOrder`),
    CONSTRAINT `FK_PairDeviceDevices_Pair`
        FOREIGN KEY (`IdPairDevice`) REFERENCES `pairdevices` (`Id`) ON DELETE CASCADE,
    CONSTRAINT `FK_PairDeviceDevices_Controller`
        FOREIGN KEY (`IdController`) REFERENCES `controllers` (`Id`) ON DELETE RESTRICT,
    CONSTRAINT `FK_PairDeviceDevices_Camera`
        FOREIGN KEY (`IdCamera`) REFERENCES `cameras` (`Id`) ON DELETE RESTRICT,
    CONSTRAINT `CHK_PairDeviceDevices_OneDevice`
        CHECK (
            (`IdController` IS NOT NULL AND `IdCamera` IS NULL)
            OR (`IdController` IS NULL AND `IdCamera` IS NOT NULL)
        ),
    CONSTRAINT `CHK_PairDeviceDevices_Role`
        CHECK (
            (`Role` = 'controller' AND `IdController` IS NOT NULL)
            OR (`Role` <> 'controller' AND `IdCamera` IS NOT NULL)
        )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 5. Контроль результата. Ожидается пустая новая таблица связей:
-- её заполнит обновлённая форма настроек сайта. Legacy-таблица сохранена.
SHOW COLUMNS FROM `PairDeviceDevices`;
SHOW CREATE TABLE `PairDeviceDevices`;
SELECT COUNT(*) AS PairDeviceMembersCount FROM `PairDeviceDevices`;
SELECT COUNT(*) AS LegacyPairDeviceMembersCount FROM `PairDeviceDevicesLegacy_20260923`;
