<?php
namespace Opencart\Admin\Controller\Extension\Opencart\Payment;
/**
 * Pagopar API - Admin Controller 
 * Documentation: https://soporte.pagopar.com/portal/es/kb/api
 * @package Opencart\Admin\Controller\Extension\Opencart\Payment
 * Developed by: Natan Teixeira  
 * Git: https://github.com/Nat4nT
 */
class Pagopar extends \Opencart\System\Engine\Controller
{
    /**
     * Index
     * @return void
     */
    public function index(): void
    {
        $this->load->language('extension/opencart/payment/pagopar');

        $this->document->setTitle($this->language->get('heading_title'));

        $data['breadcrumbs'] = [];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment')
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/opencart/payment/pagopar', 'user_token=' . $this->session->data['user_token'])
        ];

        $data['save'] = $this->url->link('extension/opencart/payment/pagopar.save', 'user_token=' . $this->session->data['user_token']);
        $data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment');

        // Order Status
        $data['payment_pagopar_order_status_id'] = (int) $this->config->get('payment_pagopar_order_status_id');
        $data['payment_pagopar_order_status_paid_id'] = (int) $this->config->get('payment_pagopar_order_status_paid_id');
        $data['payment_pagopar_order_status_cancel_id'] = (int) $this->config->get('payment_pagopar_order_status_cancel_id');

        $this->load->model('localisation/order_status');

        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        // Geo Zone
        $data['payment_pagopar_geo_zone_id'] = $this->config->get('payment_pagopar_geo_zone_id');

        $this->load->model('localisation/geo_zone');

        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

        $data['payment_pagopar_public_token'] = $this->config->get('payment_pagopar_public_token');
        $data['payment_pagopar_private_token'] = $this->config->get('payment_pagopar_private_token');

        $data['payment_pagopar_status'] = $this->config->get('payment_pagopar_status');

        $data['shipping_aex_status'] = (int) $this->config->get('shipping_aex_status');

        $data['payment_pagopar_sort_order'] = $this->config->get('payment_pagopar_sort_order');

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/opencart/payment/pagopar', $data));
    }

    /**
     * Save
     * @return void
     */
    public function save(): void
    {
        $this->load->language('extension/opencart/payment/pagopar');

        $json = [];

        if (!$this->user->hasPermission('modify', 'extension/opencart/payment/pagopar')) {
            $json['error'] = $this->language->get('error_permission');
        }

        if (!$json) {
            // Setting
            $this->load->model('setting/setting');
            $this->load->model('setting/extension');

            $status_aex = [
                'shipping_aex_status' => (int) $this->request->post['shipping_aex_status'] ?? 0,
                'shipping_aex_sort_order' => 0
            ];

            if ((int) $this->request->post['shipping_aex_status']) {
                $this->model_setting_extension->install('shipping', 'opencart', 'aex');

            } else {
                $this->model_setting_extension->uninstall('shipping', 'aex');
            }

            unset($this->request->post['shipping_aex_status']);

            $this->model_setting_setting->editSetting('payment_pagopar', $this->request->post);
            $this->model_setting_setting->editSetting('shipping', $status_aex);

            $json['success'] = $this->language->get('text_success');

            $this->load->model('extension/opencart/payment/pagopar');
            $this->model_extension_opencart_payment_pagopar->syncCities();
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
