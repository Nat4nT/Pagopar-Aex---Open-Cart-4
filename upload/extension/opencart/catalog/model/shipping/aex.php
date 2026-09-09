<?php
namespace Opencart\Catalog\Model\Extension\Opencart\Shipping;
/** 
 * AEX - Api
 * Chamada $this->load->model('extension/opencart/shipping/aex');
 * Documentação : https://soporte.pagopar.com/portal/es/kb/api
 * @package Opencart\Catalog\Model\Extension\Opencart\Shipping
 * Desenvolvido por : Natan Teixeira  
 * Git : https://github.com/Nat4nT
 */

class Aex extends \Opencart\System\Engine\Model
{

    private string $public_token = '';
    private string $private_token = '';
    private string $url = 'https://api.pagopar.com/api/calcular-flete/2.0/traer';

    public function __construct(\Opencart\System\Engine\Registry $registry)
    {
        parent::__construct($registry);
        $this->public_token = $this->config->get('payment_pagopar_public_token') ?? '';
        $this->private_token = $this->config->get('payment_pagopar_private_token') ?? '';
    }

    /**
     * Get Pagopar Cities
     * @param int $zone_id id of zone
     * @return array<string, mixed>
     */
    public function getCities(int $zone_id)
    {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "pagopar_city` WHERE `zone_id`=" . $zone_id);
        return $query->rows;
    }

    /**
     * Get Pagopar Cities
     * @param string $city id of zone
     * @return string
     */
    public function getCityByName(string $city): string
    {
        $query = $this->db->query("SELECT `ciudad_id` FROM `" . DB_PREFIX . "pagopar_city` WHERE `name` LIKE '%" . $this->db->escape($city) . "%'");
        return (string) $query->row['ciudad_id'];
    }

    /**
     * Get Quote
     * @param array<string, mixed> $address array of data
     * @return array<string, mixed>
     */
    public function getQuote(array $address): array
    {
        $this->load->language('extension/opencart/shipping/aex');

        // Geo Zone
        $this->load->model('localisation/geo_zone');
        $results = $this->model_localisation_geo_zone->getGeoZone((int) $this->config->get('shipping_item_geo_zone_id'), (int) $address['country_id'], (int) $address['zone_id']);

        if (!$this->config->get('shipping_item_geo_zone_id')) {
            $status = true;
        } elseif ($results) {
            $status = true;
        } else {
            $status = false;
        }

        $method_data = [];

        if ($status) {
            $response = $this->AexQuote();

            if (
                isset($response['status'])
                && $response['status'] == 'error'
            ) {
                return $method_data;
            }


            $quote_data = [];
            $tax_class_id = (int) $this->config->get('shipping_item_tax_class_id');

            $returned_methods = [];

            foreach ($response['compras_items'] as $item) {
                if (isset($item['opciones_envio']['metodo_aex']['opciones'])) {
                    $returned_methods = $item['opciones_envio']['metodo_aex']['opciones'];

                    foreach ($item['opciones_envio']['metodo_aex']['opciones'] as $opcao_frete) {

                        $id_servico = $opcao_frete['id'];
                        $descricao = $opcao_frete['descripcion'];
                        $custo = $opcao_frete['costo'];
                        $tempo_entrega = $opcao_frete['tiempo_entrega'];

                        $quote_data[$id_servico] = [
                            'code' => 'aex.' . $id_servico,
                            'name' => $descricao,
                            'cost' => $custo,
                            'time' => $tempo_entrega,
                            'tax_class_id' => $tax_class_id,
                            'text' => $this->currency->format($this->tax->calculate(
                                $custo,
                                $this->config->get('shipping_flat_tax_class_id'),
                                $this->config->get('config_tax')
                            ), $this->session->data['currency'])

                        ];
                    }
                }
                break;
            }


            $method_data = [
                'code' => 'aex',
                'name' => "AEX",
                'quote' => $quote_data,
                'sort_order' => $this->config->get('shipping_item_sort_order'),
                'error' => false,
                'aex_return_options' => $returned_methods
            ];
        }

        return $method_data;
    }


    private function AexQuote()
    {
        $this->load->model('checkout/cart');
        $this->load->model('tool/image');
        $this->load->model('setting/setting');

        $user_custons_field = $this->session->data['customer']['custom_field'];

        $products = $this->model_checkout_cart->getProducts();

        $totals = [];
        $taxes = $this->cart->getTaxes();
        $total = 0;

        ($this->model_checkout_cart->getTotals)($totals, $taxes, $total);

        $request_body = [
            "tipo_pedido" => "VENTA-COMERCIO",
            "fecha_maxima_pago" => date("Y-m-d H:i:s"),
            "public_key" => $this->public_token,
            "id_pedido_comercio" => 0,
            'monto_total' => (int) round($total),
            "token" => sha1($this->private_token . "CALCULAR-FLETE"),
            "descripcion_resumen" => "Geracion de Cotacion AEX",
            "comprador" => [
                "nombre" => $this->session->data['customer']['firstname'] . " " . $this->session->data['customer']['lastname'],
                "ciudad" => $this->getCityByName($this->session->data['shipping_address']['city'] ?? "Ciudad Del Este "),
                "email" => $this->session->data['customer']['email'],
                "telefono" => $user_custons_field[3],
                "tipo_documento" => "CI",
                "documento" => $user_custons_field[1],
                "direccion" => $this->session->data['shipping_address']['address_1'] . ", " . $this->session->data['shipping_address']['address_2'] . ", " . $this->session->data['shipping_address']['custom_field'][2],
                "direccion_referencia" => $this->session->data['shipping_address']['company'],
                "ruc" => $user_custons_field[4],
                "razon_social" => null
            ],
            "compras_items" => []
        ];

        /**
         * Prepare Items
         */
        foreach ($products as $prod) {
            $request_body['compras_items'][] = [
                "nombre" => $prod['model'],
                "cantidad" => $prod['quantity'],
                "ciudad" => $this->getCityByName($this->session->data['shipping_address']['city'] ?? "Ciudad Del Este "),
                "precio_total" => (int) round($prod['price'] * $prod['quantity']),
                "descripcion" => $prod['model'],
                'url_imagen' => $this->model_tool_image->resize($prod['image'], 200, 200),
                "vendedor_telefono" => $this->model_setting_setting->getValue('config_telephone'),
                "vendedor_direccion" => $this->model_setting_setting->getValue('config_address'),
                "vendedor_direccion_referencia" => $this->model_setting_setting->getValue('config_owner'),
                "vendedor_direccion_coordenadas" => "",
                "public_key" => $this->public_token,
                "categoria" => ($prod['weight'] || $prod['length'] || $prod['width'] || $prod['height']) ? 979 : "",
                "id_producto" => $prod['sku'] ?? $prod['product_id'],
                "peso" => $prod['weight'],
                "largo" => $prod['length'],
                "ancho" => $prod['width'],
                "alto" => $prod['height'],
                "opciones_envio" => [
                    "metodo_retiro" => [
                        "observacion" => $this->model_setting_setting->getValue('config_address') . " " . $this->model_setting_setting->getValue('config_open')
                    ]
                ]
            ];
        }

        return $this->sendCURL($request_body);
    }

    private function sendCURL(array $data)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->url,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);

        curl_close($ch);
        if ($error) {
            return [
                'status' => 'error',
                'message' => 'Erro na conexão cURL: ' . $error
            ];
        }

        // Decodifica o retorno JSON da API para um Array PHP
        $response_data = json_decode($response, true);
        // $response_data = $response;

        return $response_data;
    }
}