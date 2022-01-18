<?php

    /**
     * Класс для работы с Yandex.Диск
     *
     * @author Roman Grinko <rsgrinko@gmail.com>
     */
    class CYandexDrive
    {
        /**
         * @var string|null $token Токен
         */
        private static ?string $token     = null;

        /**
         * @var string|null $lastError Последняя ошибка
         */
        private static ?string $lastError = null;

        /**
         * Установка токена
         * @param string $token Токен
         */
        public static function setToken(string $token)
        {
            self::$token = $token;
        }

        /**
         * Получение последней ошибки
         *
         * @return string|null
         */
        public static function getLastError()
        {
            return self::$lastError;
        }

        /**
         * Загрузка файла на Яднекс.Диск
         *
         * @param string $file Путь к загружаемому файлу
         * @param string $uploadDir Директория загрузки
         *
         * @return bool
         */
        public static function upload(string $file, string $uploadDir)
        {
            if (is_null(self::$token)) {
                self::$lastError = 'Token not set';
                return false;
            }

            $ch = curl_init('https://cloud-api.yandex.net/v1/disk/resources/upload?path=' . urlencode($uploadDir . basename($file)));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: OAuth ' . self::$token]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HEADER, false);
            $res = curl_exec($ch);
            curl_close($ch);

            $res = json_decode($res, true);
            if (empty($res['error'])) {
                // Если ошибки нет, то отправляем файл на полученный URL.
                $fp = fopen($file, 'r');

                $ch = curl_init($res['href']);
                curl_setopt($ch, CURLOPT_PUT, true);
                curl_setopt($ch, CURLOPT_UPLOAD, true);
                curl_setopt($ch, CURLOPT_INFILESIZE, filesize($file));
                curl_setopt($ch, CURLOPT_INFILE, $fp);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_HEADER, false);
                curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($http_code == 201) {
                    return true;
                }
            }
        }

        /**
         * Загрузка файла с Яндекс.Диска
         *
         * @param string $yd_file Путь к файлу на Яндекс.Диске
         * @param string $path Путь сохранения файла
         *
         * @return bool
         */
        public static function download(string $yd_file, string $path)
        {
            $ch = curl_init('https://cloud-api.yandex.net/v1/disk/resources/download?path=' . urlencode($yd_file));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: OAuth ' . self::$token]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            $res = curl_exec($ch);
            curl_close($ch);

            $res = json_decode($res, true);
            if (empty($res['error'])) {
                $file_name = $path . '/' . basename($yd_file);
                $file      = @fopen($file_name, 'w');

                $ch = curl_init($res['href']);
                curl_setopt($ch, CURLOPT_FILE, $file);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: OAuth ' . self::$token]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_HEADER, false);
                curl_exec($ch);
                curl_close($ch);
                fclose($file);
                return true;
            } else {
                return false;
            }
        }

        /**
         * Удаление файла/папки
         *
         * @param string $path Путь
         *
         * @return bool
         */
        public static function remove(string $path)
        {
            $ch = curl_init('https://cloud-api.yandex.net/v1/disk/resources?path=' . urlencode($path) . '&permanently=true');
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: OAuth ' . self::$token]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HEADER, false);
            $res       = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if (in_array($http_code, [202, 204])) {
                return true;
            } else {
                return false;
            }
        }

        /**
         * Получение информации о Яндекс.Диске
         *
         * @return false|mixed
         */
        public static function getDriveInfo()
        {
            $ch = curl_init('https://cloud-api.yandex.net/v1/disk/');
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: OAuth ' . self::$token]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HEADER, false);
            $res = curl_exec($ch);
            curl_close($ch);

            $res = json_decode($res, true);
            if (empty($res['error'])) {
                return $res;
            } else {
                self::$lastError = $res['error'];
                return false;
            }
        }

        /**
         * Получить содержимое директории на Яндекс.Диске
         *
         * @param string $path Путь
         *
         * @return mixed
         */
        public static function getList(string $path = '/')
        {
            // Оставим только названия и тип.
            $fields = '_embedded.items.name,_embedded.items.type';
            $limit  = 100;

            $ch = curl_init('https://cloud-api.yandex.net/v1/disk/resources?path=' . urlencode($path) . '&fields=' . $fields . '&limit=' . $limit);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: OAuth ' . self::$token]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HEADER, false);
            $res = curl_exec($ch);
            curl_close($ch);

            $res = json_decode($res, true);
            return $res;
        }

        /**
         * Создать директорию на Яндекс.Диске
         *
         * @param string $path Путь
         *
         * @return mixed
         */
        public static function createFolder(string $path)
        {
            $ch = curl_init('https://cloud-api.yandex.net/v1/disk/resources/?path=' . urlencode($path));
            curl_setopt($ch, CURLOPT_PUT, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: OAuth ' . self::$token]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HEADER, false);
            $res = curl_exec($ch);
            curl_close($ch);

            $res = json_decode($res, true);
            return $res;
        }
    }
