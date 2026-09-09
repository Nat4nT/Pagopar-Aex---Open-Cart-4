<?php
namespace Opencart\Catalog\Model\Extension\Opencart\Payment;
/**
 * Pagopar API 
 * Chamada $this->load->model('extension/opencart/payment/pagopar');
 * Documentação : https://soporte.pagopar.com/portal/es/kb/api
 * @package Opencart\Catalog\Model\Extension\Opencart\Payment
 * Desenvolvido por : Natan Teixeira  
 * Git : https://github.com/Nat4nT
 */

class Pagopar extends \Opencart\System\Engine\Model
{
    private string $public_token = '';
    private string $private_token = '';
    private string $url = 'https://api.pagopar.com/api/comercios/2.0/iniciar-transaccion';
    private string $traer = 'https://api.pagopar.com/api/pedidos/1.1/traer';


    public function __construct(\Opencart\System\Engine\Registry $registry)
    {
        parent::__construct($registry);
        $this->public_token = $this->config->get('payment_pagopar_public_token') ?? '';
        $this->private_token = $this->config->get('payment_pagopar_private_token') ?? '';
    }

    /**
     * Get Methods
     * @param array<string, mixed> $address array of data
     * @return array<string, mixed>
     */
    public function getMethods(array $address = []): array
    {
        $this->load->language('extension/opencart/payment/pagopar');

        if ($this->cart->hasSubscription()) {
            $status = false;
        } elseif (!$this->config->get('config_checkout_payment_address')) {
            $status = true;
        } elseif (!$this->config->get('payment_pagopar_geo_zone_id')) {
            $status = true;
        } else {
            // Geo Zone
            $this->load->model('localisation/geo_zone');

            $results = $this->model_localisation_geo_zone->getGeoZone((int) $this->config->get('payment_pagopar_geo_zone_id'), (int) $address['country_id'], (int) $address['zone_id']);

            if ($results) {
                $status = true;
            } else {
                $status = false;
            }
        }

        $method_data = [];

        if ($status) {


            $option_data['pagopar'] = [
                'code' => 'pagopar.pagopar',
                'name' => $this->language->get('heading_title')
            ];

            $method_data = [
                'code' => 'pagopar',
                'name' => $this->language->get('heading_title'),
                'option' => $option_data,
                'sort_order' => $this->config->get('payment_pagopar_sort_order')
            ];
        }

        return $method_data;
    }

    public function PagoparPaymentLinkGenerate()
    {
        $this->load->model('checkout/cart');
        $this->load->model('extension/opencart/shipping/aex');
        $this->load->model('tool/image');
        $this->load->model('setting/setting');

        $user_custons_field = $this->session->data['customer']['custom_field'];
        $products = $this->model_checkout_cart->getProducts();

        $totals = [];
        $taxes = $this->cart->getTaxes();
        $total = 0;

        ($this->model_checkout_cart->getTotals)($totals, $taxes, $total);

        $request_body = [
            "token" => sha1($this->private_token . $this->session->data['order_id'] . strval($total)),
            "comprador" => [
                "nombre" => $this->session->data['customer']['firstname'] . " " . $this->session->data['customer']['lastname'],
                "ciudad" => $this->model_extension_opencart_shipping_aex->getCityByName($this->session->data['shipping_address']['city'] ?? "Ciudad Del Este "),
                "email" => $this->session->data['customer']['email'],
                "telefono" => $user_custons_field[3],
                "coordenadas" => "",
                "tipo_documento" => "CI",
                "documento" => preg_replace('/[^0-9]/', '', $user_custons_field[1]),
                "direccion" => $this->session->data['shipping_address']['address_1'] . ", " . $this->session->data['shipping_address']['address_2'] . ", " . $this->session->data['shipping_address']['custom_field'][2],
                "direccion_referencia" => $this->session->data['shipping_address']['company'],
                "ruc" => preg_replace('/[^0-9]/', '', $user_custons_field[4]),
                "razon_social" => $this->session->data['customer']['firstname'] . " " . $this->session->data['customer']['lastname']
            ],
            "public_key" => $this->public_token,
            'monto_total' => $total,
            "tipo_pedido" => "VENTA-COMERCIO",
            "fecha_maxima_pago" => date("Y-m-d H:i:s", strtotime('+20 minutes')),
            "id_pedido_comercio" => $this->session->data['order_id'],
            "descripcion_resumen" => "Geracion de link para pago.",

            "compras_items" => []
        ];

        /**
         * Prepare Items
         */
        foreach ($products as $index => $prod) {

            $item = [
                "nombre" => $prod['model'],
                "cantidad" => (int) $prod['quantity'],
                "ciudad" => $this->model_extension_opencart_shipping_aex->getCityByName($this->session->data['shipping_address']['city'] ?? "Ciudad Del Este"),
                "precio_total" => (int) round($prod['price'] * $prod['quantity']),
                "descripcion" => $prod['model'],
                "url_imagen" => $this->model_tool_image->resize($prod['image'] ?? 'placeholder.png', 200, 200),
                "vendedor_telefono" => $this->model_setting_setting->getValue('config_telephone'),
                "vendedor_direccion" => $this->model_setting_setting->getValue('config_address'),
                "vendedor_direccion_referencia" => $this->model_setting_setting->getValue('config_owner'),
                "vendedor_direccion_coordenadas" => "",
                "public_key" => $this->public_token,
                "categoria" => (
                    $prod['weight'] ||
                    $prod['length'] ||
                    $prod['width'] ||
                    $prod['height']
                ) ? 979 : "",

                "id_producto" => $prod['sku'] ?? $prod['product_id'],

                "peso" => number_format((float) ($prod['weight'] ?? 0), 2, '.', ''),
                "largo" => number_format((float) ($prod['length'] ?? 0), 2, '.', ''),
                "ancho" => number_format((float) ($prod['width'] ?? 0), 2, '.', ''),
                "alto" => number_format((float) ($prod['height'] ?? 0), 2, '.', ''),
                "opciones_envio" => [
                    "metodo_retiro" => [
                        "observacion" => "Recogida local",
                        "costo" => 0,
                        "tiempo_entrega" => 0
                    ]
                ]
            ];

            if (isset($this->session->data['shipping_method'])) {

                $shipping = $this->session->data['shipping_method'];
                $shipping_code = $shipping['code'] ?? 'aex';
                $shipping_selected = explode('.', $shipping_code)[0] ?? 'aex';
                $shipping_cost = (float) ($shipping['cost'] ?? 0);
                $time = $shipping['time'];
                $item['costo_envio'] = $index == 0 ? $shipping_cost : 0;
                $item['envio_seleccionado'] = 'aex';
                // $item['envio_seleccionado'] = explode('.', $shipping_code)[1];

                if (isset($this->session->data['shipping_methods'][$shipping_selected])) {

                    $options = [];
                    foreach ($this->session->data['shipping_methods'][$shipping_selected]['aex_return_options'] as $method) {
                        $options[] = [
                            "id" => $method['id'],
                            "descripcion" => $method['descripcion'],
                            "costo" => ($index != 0) ? 0 : $method['costo'],
                            "tiempo_entrega" => $method['tiempo_entrega']
                        ];
                    }

                    $item['opciones_envio']['metodo_aex'] = [
                        "id" => explode('.', $shipping_code)[1],
                        "costo" => $index == 0 ? $shipping_cost : 0,
                        "tiempo_entrega" => $time,
                        "opciones" => $options
                    ];
                }
            }
            $request_body['compras_items'][] = $item;
        }

        if (isset($this->session->data['payment_method']['method_payment'])) {
            $request_body['forma_pago'] = $this->session->data['payment_method']['method_payment'];
        }


        return $this->sendCURL($request_body, $this->url);

    }

    private function sendCURL(array $data, string $url = '')
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
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
        return $response_data;
    }

    public function addPagoparOrder(int $order_id, string $pagopar_order_id, string $token): void
    {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "pagopar_order` SET `order_id` = '" . (int) $order_id . "', `pagopar_order_number` = '" . (string) $pagopar_order_id . "' , `pagopar_order_token`='" . (string) $token . "'");
    }

    public function getOrderId(string $pagopar_order_id)
    {
        $query = $this->db->query("SELECT `order_id` FROM `" . DB_PREFIX . "pagopar_order` WHERE `pagopar_order_number` = '" . (string) $pagopar_order_id . "'");
        return (int) $query->row['order_id'];
    }

    public function getPagoparOrdersNotPaid(int $payment_status_id): array
    {
        $query = $this->db->query('SELECT DISTINCT po.pagopar_order_token,  po.order_id FROM ' . DB_PREFIX . 'pagopar_order po LEFT JOIN ' . DB_PREFIX . 'order o ON (po.order_id = o.order_id) WHERE o.date_modified < DATE_SUB(NOW(), INTERVAL 20 MINUTE) AND `order_status_id`=' . (int) $payment_status_id);
        return $query->rows;
    }

    public function SyncOrderHistory(int $payment_status_id, int $payment_status_paid_id, int $payment_status_canceled_id)
    {
        $orders = $this->getPagoparOrdersNotPaid($payment_status_id);
        $body_data = [
            "token" => sha1($this->private_token . "CONSULTA"),
            "token_publico" => $this->public_token
        ];

        $total = count($orders);
        $success = 0;
        $error = 0;

        if ($total) {
            foreach ($orders as $order) {
                $body_data['hash_pedido'] = $order['pagopar_order_token'];
                $curl_return = $this->sendCURL($body_data, $this->traer);

                if (isset($curl_return['status']) && $curl_return['status'] === 'error') {
                    $error++;
                } else {
                    if (isset($curl_return['respuesta']) && $curl_return['respuesta']) {
                        $order_pagopar_info = $curl_return['resultado'][0];
                        $this->load->model('checkout/order');

                        $log = new \Opencart\System\Library\Log('pagopar/' . $order_pagopar_info['numero_pedido']);
                        $log->write(json_encode($curl_return, JSON_PRETTY_PRINT));

                        if ($order_pagopar_info['pagado']) {
                            $this->model_checkout_order->addHistory($order['order_id'], $payment_status_paid_id, "Pago em Pagopar");
                        } else {
                            $this->model_checkout_order->addHistory($order['order_id'], $payment_status_canceled_id, "Pago Cancelado");
                        }

                        $success++;
                    } else {
                        $error++;
                    }
                }
            }
        }

        return [
            "success" => $success,
            "errors" => $error,
            "total" => $total
        ];

    }


}