<?php

namespace FileManager;

use App\Helper;

class Settings
{
    private static string $fileFieldName = 'file';
    private static string $downloadGetParameter = 'get_file';
    private static string $deleteGetParameter = 'delete_file';
    private static string $storageDir = '';

    // DB
    private static string $dbType = 'mysql';
    private static string $dbHost = 'localhost';
    private static string $dbPort = '3306';
    private static string $dbName = '';
    private static string $dbUser = '';
    private static string $dbPassword = '';

    /**
     * @return string
     */
    public static function getFileFieldName(): string
    {
        return self::$fileFieldName;
    }

    /**
     * @param  string  $fileFieldName
     */
    public static function setFileFieldName(string $fileFieldName): void
    {
        self::$fileFieldName = $fileFieldName;
    }

    /**
     * @return string
     */
    public static function getDownloadGetParameter(): string
    {
        return self::$downloadGetParameter;
    }

    /**
     * @param  string  $downloadGetParameter
     */
    public static function setDownloadGetParameter(string $downloadGetParameter): void
    {
        self::$downloadGetParameter = $downloadGetParameter;
    }

    /**
     * @return string
     */
    public static function getDeleteGetParameter(): string
    {
        return self::$deleteGetParameter;
    }

    /**
     * @param  string  $deleteGetParameter
     */
    public static function setDeleteGetParameter(string $deleteGetParameter): void
    {
        self::$deleteGetParameter = $deleteGetParameter;
    }

    /**
     * @return string
     */
    public static function getStorageDir(): string
    {
        return self::$storageDir;
    }

    /**
     * @param  string  $storageDir
     */
    public static function setStorageDir(string $storageDir): void
    {
        self::$storageDir = trim($storageDir, '/\\ ');
    }

    /**
     * @return string
     */
    public static function getDbType(): string
    {
        return self::$dbType;
    }

    /**
     * @param  string  $dbType
     */
    public static function setDbType(string $dbType): void
    {
        self::$dbType = $dbType;
    }

    /**
     * @return string
     */
    public static function getDbHost(): string
    {
        return self::$dbHost;
    }

    /**
     * @param  string  $dbHost
     */
    public static function setDbHost(string $dbHost): void
    {
        self::$dbHost = $dbHost;
    }

    /**
     * @return string
     */
    public static function getDbPort(): string
    {
        return self::$dbPort;
    }

    /**
     * @param  string  $dbPort
     */
    public static function setDbPort(string $dbPort): void
    {
        self::$dbPort = $dbPort;
    }

    /**
     * @return string
     */
    public static function getDbName(): string
    {
        return self::$dbName;
    }

    /**
     * @param  string  $dbName
     */
    public static function setDbName(string $dbName): void
    {
        self::$dbName = $dbName;
    }

    /**
     * @return string
     */
    public static function getDbUser(): string
    {
        return self::$dbUser;
    }

    /**
     * @param  string  $dbUser
     */
    public static function setDbUser(string $dbUser): void
    {
        self::$dbUser = $dbUser;
    }

    /**
     * @return string
     */
    public static function getDbPassword(): string
    {
        return self::$dbPassword;
    }

    /**
     * @param  string  $dbPassword
     */
    public static function setDbPassword(string $dbPassword): void
    {
        self::$dbPassword = $dbPassword;
    }
}
