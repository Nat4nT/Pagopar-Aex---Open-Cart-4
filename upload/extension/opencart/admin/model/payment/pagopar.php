<?php
namespace Opencart\Admin\Model\Extension\Opencart\Payment;
/**
 * Pagopar API 
 * Call: $this->load->model('extension/opencart/payment/pagopar');
 * Documentation: https://soporte.pagopar.com/portal/es/kb/api
 * @package Opencart\Catalog\Model\Extension\Opencart\Payment
 * Developed by: Natan Teixeira  
 * Git: https://github.com/Nat4nT
 */
class Pagopar extends \Opencart\System\Engine\Model
{
    private string $public_token = '';
    private string $private_token = '';
    private string $url = 'https://api.pagopar.com/api/pedidos/1.1/traer';

    public function __construct(\Opencart\System\Engine\Registry $registry)
    {
        parent::__construct($registry);
        $this->public_token = $this->config->get('payment_pagopar_public_token') ?? '';
        $this->private_token = $this->config->get('payment_pagopar_private_token') ?? '';
    }

    /**
     * Install
     *
     * @return void
     */
    public function install(): void
    {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "pagopar_city` (
                `pagopar_city_id` INT(11) NOT NULL AUTO_INCREMENT,
                `ciudad_id` INT(11) NOT NULL,
                `zone_id` INT(11) NULL,
                `name` VARCHAR(255) NOT NULL,
                `status` TINYINT(1) NOT NULL DEFAULT 1,
                `sort_order` INT(11) NOT NULL DEFAULT 0,

                PRIMARY KEY (`pagopar_city_id`),
                UNIQUE KEY `uk_ciudad_id` (`ciudad_id`),
                KEY `idx_zone_id` (`zone_id`),
                KEY `idx_status` (`status`)
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "pagopar_order` (
                `pagopar_order_id` INT(11) NOT NULL AUTO_INCREMENT,
                `order_id` INT(11) NOT NULL,
                `pagopar_order_number` VARCHAR(250) NOT NULL,
                `pagopar_order_token` VARCHAR(250) NOT NULL,
                PRIMARY KEY (`pagopar_order_id`),
                UNIQUE KEY `uk_order_id` (`order_id`)
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");

        $description = "Automatic status change in case of error in pagopar's response";

        $this->db->query("INSERT INTO `" . DB_PREFIX . "cron` SET 
                `code` = 'pagopar', 
                `description` = '" . $this->db->escape($description) . "', 
                `cycle` = 'hour', 
                `action` = 'cron/pagopar', 
                `status` = 1,
                `date_added` = NOW(),
                `date_modified` = NOW()");
        $log_dir = DIR_LOGS . 'pagopar/';

        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
    }

    /**
     * Uninstall
     *
     * @return void
     */
    public function uninstall(): void
    {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "pagopar_city`");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "pagopar_order`");
    }

    /**
     * Synchronize Pagopar cities
     *
     * @return bool
     */
    public function syncCities(): bool
    {
        if (!$this->public_token || !$this->private_token) {
            return false;
        }

        $token = sha1($this->private_token . 'CIUDADES');

        $data = [
            'token' => $token,
            'token_publico' => $this->public_token
        ];

        $ch = curl_init(
            'https://api.pagopar.com/api/ciudades/1.1/traer'
        );

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            curl_close($ch);
            return false;
        }

        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($httpCode !== 200) {
            return false;
        }

        $result = json_decode($response, true);


        if (
            !is_array($result) ||
            empty($result['respuesta']) ||
            !isset($result['resultado']) ||
            !is_array($result['resultado'])
        ) {
            return false;
        }

        $departmentZones = $this->getDepartmentZones();
        $departmentCities = $this->getDepartmentCities();

        $cityMap = [];

        foreach ($departmentCities as $departmentId => $cities) {

            if (!isset($departmentZones[$departmentId])) {
                continue;
            }

            $zoneId = $departmentZones[$departmentId];

            foreach ($cities as $cityName) {
                $cityMap[$cityName] = $zoneId;
            }
        }

        foreach ($result['resultado'] as $city) {

            if (
                !isset($city['ciudad']) ||
                !isset($city['descripcion'])
            ) {
                continue;
            }

            $ciudadId = (int) $city['ciudad'];
            $cityName = trim((string) $city['descripcion']);



            $zoneId = $cityMap[$cityName] ?? null;

            $existing = $this->db->query("
            SELECT
                `pagopar_city_id`,
                `zone_id`
            FROM `" . DB_PREFIX . "pagopar_city`
            WHERE `ciudad_id` = '" . $ciudadId . "'
            LIMIT 1
        ");

            if ($existing->num_rows) {

                $this->db->query("
                UPDATE `" . DB_PREFIX . "pagopar_city`
                SET
                    `name` = '" . $this->db->escape($cityName) . "'
                WHERE `ciudad_id` = '" . $ciudadId . "'
            ");


                continue;
            }

            if ($zoneId !== null) {

                $this->db->query("
                INSERT INTO `" . DB_PREFIX . "pagopar_city`
                SET
                    `ciudad_id` = '" . $ciudadId . "',
                    `zone_id` = '" . (int) $zoneId . "',
                    `name` = '" . $this->db->escape($cityName) . "'
            ");

            } else {

                $this->db->query("
                INSERT INTO `" . DB_PREFIX . "pagopar_city`
                SET
                    `ciudad_id` = '" . $ciudadId . "',
                    `zone_id` = NULL,
                    `name` = '" . $this->db->escape($cityName) . "'
            ");
            }
        }

        return true;
    }

    /**
     * Returns the relationship between the legacy department ID
     * and the OpenCart zone_id.
     *
     * @return array<int, int>
     */
    private function getDepartmentZones(): array
    {
        return [
            0 => 2512, // Asuncion
            1 => 2518, // Concepcion
            2 => 2526, // San Pedro
            3 => 2519, // Cordillera
            4 => 2520, // Guaira
            5 => 2514, // Caaguazu
            6 => 2515, // Caazapa
            7 => 2521, // Itapua
            8 => 2522, // Misiones
            9 => 2524, // Paraguari
            10 => 2510, // Alto Parana
            11 => 2517, // Central
            12 => 2523, // Neembucu
            13 => 2511, // Amambay
            14 => 2516, // Canindeyu
            15 => 2525, // Presidente Hayes
            16 => 2513, // Boqueron
        ];
    }

    /**
     * Returns the legacy relationship between departments and cities.
     *
     * @return array<int, array<int, string>>
     */
    private function getDepartmentCities(): array
    {
        return [
            0 => [
                'Asuncion'
            ],

            1 => [
                'Belen',
                'Concepcion',
                'Horqueta',
                'Loreto',
                'Vallemi',
                "Yby Ya'u"
            ],

            2 => [
                '25 De Diciembre',
                'Antequera',
                'Capiibary',
                'Chore',
                'Cruce Liberación',
                'Gral. Elizardo Aquino',
                'Gral. Resquín',
                'Guayaybi',
                'Itacurubi Del Rosario',
                'Lima',
                'San Pedro del Ycuamandiyú',
                'Santa Rosa del Aguaray',
                'Santani',
                'Tacuati',
                'Yataity Del Norte'
            ],

            3 => [
                '1ro. De marzo',
                'Altos',
                'Atyra',
                'Arroyos Y Esteros',
                'Caacupe',
                'Caraguatay',
                'Emboscada',
                'Eusebio Ayala',
                'Isla Pucu',
                'Itacurubi De La Cordillera',
                'Mbocayaty Del Yhaguy',
                'Piribebuy',
                'San Bernardino',
                'Santa Elena',
                'San José Obrero',
                'Tobati',
                'Valenzuela'
            ],

            4 => [
                'Borja',
                'Colonia Independencia',
                'Coronel Martinez',
                'Dr. Botrel',
                'Eugenio A. Garay',
                'Felix Perez Cardozo',
                'Itape',
                'Iturbe',
                'Ñumi',
                'Jose Fassardi',
                'Mauricio Jose Troche',
                'Mbocayaty Del Guaira',
                'Natalicio Talavera',
                'Numi',
                'Paso Yobai',
                'San Salvador',
                'Tebicuary',
                'Villarrica',
                'Yataity Del Guaira'
            ],

            5 => [
                'Campo 9',
                'Caaguazu',
                'Carayao',
                'Cecilio Báez',
                'Coronel Oviedo',
                'Juan Manuel Frutos (Pastoreo)',
                'Nueva Londres',
                'Repatriacion',
                'San Joaquin',
                'San Jose De Los Arroyos',
                'Simon Bolivar',
                'Santa Rosa Del Mbutuy',
                'Vaqueria',
                'Yhu'
            ],

            6 => [
                'Buena Vista',
                'Caazapa',
                'Fulgencio Yegros',
                'Higinio Morinigo',
                'Maciel',
                'Moises Bertoni',
                'San Juan Nepomuceno',
                'Yuty'
            ],

            7 => [
                'Bella Vista Sur',
                'Cambyreta',
                'Capitan Meza',
                'Capitan Miranda',
                'Carmen Del Parana',
                'Colonia Fram',
                'Coronel Bogado',
                'Edelira 21',
                'Edelira 28',
                'Encarnacion',
                'Gral. Artigas',
                'Gral. Delgado',
                'Hohenau',
                'Jesus',
                'Jose Leandro Oviedo',
                'Kimex (Colonia Carlos A. López)',
                'La Paz',
                'Mayor Otaño',
                'Maria Auxiliadora',
                'Natalio',
                'Obligado',
                'Pirapo',
                'San Juan Del Parana',
                'San Pedro Del Parana',
                'San Rafael Del Parana',
                'Trinidad',
                'Yatytay'
            ],

            8 => [
                'Ayolas',
                'San Patricio',
                'San Ignacio Misiones',
                'San Juan Bautista',
                'Santiago Misiones',
                'Santa Rosa Misiones',
                'Santa María Misiones'
            ],

            9 => [
                'Acahay',
                'Bernardino Caballero',
                'Caapucu',
                'Carapegua',
                'Escobar',
                'Paraguari',
                'Pirayu',
                'Quiindy',
                'San Roque Gonzalez',
                'Sapucai',
                'Yaguaron',
                'Ybycui',
                'Ybytymi'
            ],

            10 => [
                'Cedrales',
                'Ciudad Del Este',
                'Colonia Yguazu',
                'Curupayty',
                'Hernandarias',
                'Itakyry',
                'Juan E. OLeary',
                'Juan León Mallorquin',
                'Minga Guazu',
                'Minga Pora',
                'Naranjal',
                'Puerto Pdte. Franco',
                'San Alberto',
                'San Cristobal',
                'Santa Fe Del Parana',
                'Santa Rita',
                "Juan E. O'Leary",
                'Santa Rosa Del Monday'
            ],

            11 => [
                'Aregua',
                'Capiata',
                'Fernando De La Mora',
                'Guarambare',
                'Ita',
                'Itagua',
                'José Augusto Saldivar',
                'Lambare',
                'Limpio',
                'Luque',
                'Mariano Roque Alonso',
                'Ñemby',
                'Nueva Italia',
                'San Antonio',
                'San Lorenzo',
                'Villa Elisa',
                'Villeta',
                'Ypane',
                'Ypacarai'
            ],

            12 => [
                'Alberdi',
                'Villa Oliva',
                'Pilar'
            ],

            13 => [
                'Capitan Bado',
                'Bella Vista Norte',
                'Pedro Juan Caballero'
            ],

            14 => [
                'Corpus Christi',
                'Yasy Kañy',
                'Cruce Guaraní',
                'Curuguaty',
                'Katuete',
                'La Paloma',
                'Nueva Esperanza',
                'Puente Kyha',
                'Salto Del Guaira',
                'Yasy Kany'
            ],

            15 => [
                'Benjamin Aceval',
                'Villa Hayes'
            ],

            16 => [
                'Filadelfia',
                'Loma Plata',
                'Colonia Neuland'
            ],
        ];
    }

}
