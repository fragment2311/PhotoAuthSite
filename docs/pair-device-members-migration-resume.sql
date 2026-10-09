-- ПРОДОЛЖЕНИЕ частично выполненной миграции PairDeviceDevices.
-- Не запускайте первоначальный pair-device-members-migration.sql повторно:
-- его шаг удаления IdPairDeviceDevices уже выполнен.
--
-- Подтверждённое текущее состояние:
-- - pairdevices больше не содержит IdPairDeviceDevices;
-- - резервные таблицы pairdevicesbackup_20260923 и
--   pairdevicedevicesbackup_20260923 существуют;
-- - pair_device_devices содержит старые 6 строк, но больше не имеет FK.

-- 1. Контроль перед продолжением.
SELECT COUNT(*) AS PairDevicesCount FROM `pairdevices`;
SELECT COUNT(*) AS LegacyRowsToPreserve FROM `pair_device_devices`;
SELECT COUNT(*) AS PairDevicesBackupCount FROM `pairdevicesbackup_20260923`;
SELECT COUNT(*) AS PairDeviceDevicesBackupCount FROM `pairdevicedevicesbackup_20260923`;

-- 2. Сохраняем старую таблицу с её данными под отдельным именем.
-- На этом сервере MySQL приведёт имя к нижнему регистру; это нормально.
RENAME TABLE `pair_device_devices` TO `PairDeviceDevicesLegacy_20260923`;

-- 3. Создаём новую таблицу состава устройств точки.
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

-- 4. Проверка результата. Новая таблица должна быть пустой.
SHOW CREATE TABLE `PairDeviceDevices`;
SELECT COUNT(*) AS NewPairDeviceMembersCount FROM `PairDeviceDevices`;
SELECT COUNT(*) AS PreservedLegacyRows FROM `PairDeviceDevicesLegacy_20260923`;
