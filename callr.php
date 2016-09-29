<?php
/**
* Callr
*
* Send SMS notifications on order updates
*
*    @author Callr SAS <integrations@callr.com>
*    @copyright  2016 Callr SAS
*    @license    https://opensource.org/licenses/MIT
*/

class Callr extends Module
{
    public function __construct()
    {
        require_once 'lib/vendor/autoload.php';

        $this->name = 'callr';
        $this->tab = 'emailing';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Callr');
        $this->description = $this->l('Send SMS notifications on order updates.');

        $this->version = '1.0.0';
        $this->author = 'Callr';
    }

    /**
     * @return bool success
     **/
    public function install()
    {
        return parent::install() &&
            $this->registerHook('backOfficeHeader') &&
            $this->registerHook('newOrder') &&
            $this->registerHook('actionOrderStatusUpdate') &&
            Configuration::updateValue(Tools::strtoupper($this->name), '{}');
    }

    /**
     * @return bool success
     **/
    public function uninstall()
    {
        return parent::uninstall() && Configuration::deleteByName(strtoupper($this->name));
    }

    /**
     * Module admin page.
     */
    public function getContent()
    {
        return $this->processForm().$this->renderForm();
    }

    /**
     * Load JS and CSS.
     */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJS($this->_path.'/views/js/callr.js');
            $this->context->controller->addCSS($this->_path.'/views/css/callr.css');
        }
    }

    /**
     * Process admin form.
     *
     * @return string html form process result (errors or success message)
     */
    protected function processForm()
    {
        $output = '';
        if (Tools::isSubmit('submit'.$this->name)) {
            $errors = array();
            $data = $this->getConfigFieldsValues();
            foreach ($data as $key => $value) {
                switch ($key) {
                    case 'admin_enabled':
                    case 'callr_debug':
                        // Checkbox
                        if ($value) {
                            $data[$key] = true;
                        }
                        break;
                    case 'admin_phone':
                        // Phone or list of phone numbers
                        if (!empty($value)) {
                            $admin_phones = array();
                            $numbers = explode(',', $value);
                            foreach ($numbers as $number) {
                                $number = trim($number);
                                if (preg_match('/^\+[1-9][0-9]{5,14}$/', $number)) {
                                    $admin_phones[] = $number;
                                } else {
                                    $errors[] = $this->displayError($this->l('Invalid phone number.'));
                                }
                            }
                            $data[$key] = implode($admin_phones, ', ');
                        }
                        break;
                    case 'callr_sender':
                        // Sender ID
                        if (!empty($value)) {
                            if (!preg_match('/^[ a-zA-Z0-9_-]+$/', $value)) {
                                $errors[] = $this->displayError($this->l('Invalid sender ID.'));
                            }
                        }
                        break;
                    default:
                        if (Tools::substr($key, 0, 22) == 'customer_notification_') {
                            // Checkbox
                            if ($value) {
                                $data[$key] = true;
                            }
                        } else {
                            // Generic string
                        }
                }
            }
            if (empty($errors)) {
                Configuration::updateValue(strtoupper($this->name), Tools::jsonEncode($data));
                $output .= $this->displayConfirmation($this->l('Settings updated'));
            } else {
                $output .= implode($errors);
            }
        }

        return $output;
    }

    /**
     * Admin form.
     *
     * @return string html form content
     */
    protected function renderForm()
    {
        $statuses = $this->getStatuses();
        $checkboxes = array();
        $textareas = array(
            array(
                'type' => 'textarea',
                'label' => $this->l('Default'),
                'name' => 'customer_message_default',
                'desc' => $this->l('This is the default message for all statuses. You can override it below.'),
            ),
        );

        foreach ($statuses as $status) {
            $checkboxes[] = array(
                'id' => $status['id_order_state'],
                'name' => $status['name'],
            );
            $textareas[] = array(
                'type' => 'textarea',
                'label' => $status['name'],
                'name' => 'customer_message_'.$status['id_order_state'],
            );
        }

        $tokens = '<p>'.$this->l('Use the following tokens to customize the SMS messages:').'</p>';
        $tokens .= '<code>[first_name]</code> – '.$this->l('Customer first name').'<br>';
        $tokens .= '<code>[last_name]</code> – '.$this->l('Customer last name').'<br>';
        $tokens .= '<code>[shop_name]</code> – '.$this->l('Shop name').'<br>';
        $tokens .= '<code>[order_id]</code> – '.$this->l('Order reference').'<br>';
        $tokens .= '<code>[order_amount]</code> – '.$this->l('Order amount').'<br>';
        $tokens .= '<code>[order_currency]</code> – '.$this->l('Order currency').'<br>';
        $tokens .= '<code>[order_status]</code> – '.$this->l('Order status').'<br>';

        $fields_form = array(

            array(
                'form' => array(
                    'legend' => array(
                        'title' => $this->l('Admin notifications'),
                        'icon' => 'icon-cogs',
                    ),
                    'description' => html_entity_decode($tokens),
                    'input' => array(
                        array(
                            'type' => 'checkbox',
                            'label' => $this->l('Notifications'),
                            'name' => 'admin',
                            'desc' => $this->l(
                                'Check this box if you want to be notified by SMS when a new order is made.'
                            ),
                            'values' => array(
                                'query' => array(
                                    array(
                                        'id' => 'enabled',
                                        'name' => $this->l('Enabled'),
                                    ),
                                ),
                                'name' => 'name',
                                'id' => 'id',
                            ),
                        ),
                        array(
                            'type' => 'text',
                            'label' => $this->l('Admin phone number'),
                            'name' => 'admin_phone',
                            'desc' => $this->l('Your mobile phone number in E.164 format. Example: "+3384140055".')
                                .'<br>'
                                .$this->l(
                                    'You can separate multiple numbers with commas. ' .
                                    'Example:  "+16467890800, +33678912345".'
                                ),
                        ),
                        array(
                            'type' => 'textarea',
                            'label' => $this->l('Admin message'),
                            'name' => 'admin_message',
                            'desc' => $this->l('The message you will receive when an new order is made.'),
                        ),
                    ),
                    'submit' => array(
                        'title' => $this->l('Save'),
                    ),
                ),
            ),

            array(
                'form' => array(
                    'legend' => array(
                        'title' => $this->l('Customer notifications'),
                        'icon' => 'icon-cogs',
                    ),
                    'description' => html_entity_decode($tokens),
                    'input' => array(
                        array(
                            'type' => 'checkbox',
                            'label' => $this->l('Notifications'),
                            'name' => 'customer_notification',
                            'desc' => $this->l(
                                'Check the statuses for which you want your customers to ' .
                                'be notified by SMS when their order is updated.'
                            ),
                            'values' => array(
                                'query' => $checkboxes,
                                'name' => 'name',
                                'id' => 'id',
                            ),
                        ),
                    ),
                    'submit' => array(
                        'title' => $this->l('Save'),
                    ),
                ),
            ),

            array(
                'form' => array(
                    'legend' => array(
                        'title' => $this->l('Callr settings'),
                        'icon' => 'icon-cogs',
                    ),
                    'input' => array(
                        array(
                            'type' => 'text',
                            'label' => $this->l('Username'),
                            'name' => 'callr_username',
                            'desc' => $this->l('Your Callr username. You can register an account at ') .
                                '<a href="http://callr.com" target="_blank">http://callr.com</a>.',
                        ),
                        array(
                            'type' => 'text',
                            'label' => $this->l('Password'),
                            'name' => 'callr_password',
                            'desc' => $this->l('Your Callr password. You can register an account at ') .
                                '<a href="http://callr.com" target="_blank">http://callr.com</a>.',
                        ),
                        array(
                            'type' => 'text',
                            'label' => $this->l('Sender ID'),
                            'name' => 'callr_sender',
                            'desc' => $this->l(
                                'The SMS sender. If empty, a shared shortcode will be automatically ' .
                                'selected according to the destination carrier. ' .
                                'Otherwise, it must be either a dedicated shortcode, or alphanumeric ' .
                                '(at least one character - cannot be digits only).'
                            ) .
                                '<br>' . $this->l(
                                    'Max length: 11 characters. Depending on your account configuration, '.
                                    ' you may have to ask Callr support team to authorize custom Sender IDs. '.
                                    '"SMS" is always authorized.'
                                ),
                        ),
                        array(
                            'type' => 'checkbox',
                            'label' => $this->l('Debug'),
                            'name' => 'callr',
                            'desc' => $this->l(
                                'Check this box if you are having issues sending SMS.
                                This will append errors to the log.'
                            ),
                            'values' => array(
                                'query' => array(
                                    array(
                                        'id' => 'debug',
                                        'name' => $this->l('Log errors'),
                                    ),
                                ),
                                'name' => 'name',
                                'id' => 'id',
                            ),
                        ),
                    ),
                    'submit' => array(
                        'title' => $this->l('Save'),
                    ),
                ),
            ),

            array(
                'form' => array(
                    'legend' => array(
                        'title' => $this->l('Send test SMS'),
                        'icon' => 'icon-cogs',
                    ),
                    'input' => array(
                        array(
                            'type' => 'text',
                            'label' => $this->l('Test phone number'),
                            'name' => 'test_phone',
                            'desc' => $this->l('A mobile phone number in E.164 format. Example: "+3384140055".'),
                        ),
                        array(
                            'type' => 'textarea',
                            'label' => $this->l('Admin message'),
                            'name' => 'test_message',
                            'desc' => $this->l('A test message.'),
                        ),
                    ),
                    'submit' => array(
                        'title' => $this->l('Send'),
                        'icon' => 'icon-arrow-right',
                    ),
                ),
            ),

        );

        $fields_form[1]['form']['input'] = array_merge($fields_form[1]['form']['input'], $textareas);

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ?
            Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submit'.$this->name;
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) .
            '&configure=' . $this->name .'&tab_module=' .$this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm($fields_form);
    }

    /**
     * @return array settings
     */
    protected function getConfigFieldsValues()
    {
        $statuses = $this->getStatuses();
        $settings = $this->getSettings();

        // Default values
        $default = array(
            'admin_enabled' => false,
            'admin_phone' => '',
            'admin_message' => 'New order #[order_id] on [shop_name] for [order_currency][order_amount].',
            'customer_message_default' => 'Dear [first_name], your order #[order_id] ' .
                'on [shop_name] is now: [order_status].',
            'callr_username' => '',
            'callr_password' => '',
            'callr_sender' => 'SMS',
            'callr_debug' => true,
            'test_phone' => '',
            'test_message' => '',
        );
        foreach ($statuses as $status) {
            $default['customer_notification_'.$status['id_order_state']] = false;
            $default['customer_message_'.$status['id_order_state']] = '';
        }

        // Override with settings from database
        $settings = array_merge($default, $settings);

        // Override with settings from form
        if (Tools::isSubmit('submit'.$this->name)) {
            foreach ($settings as $key => $value) {
                if (!array_key_exists($key, $_POST)) {
                    $value = false; // unchecked checkbox
                }
                $settings[$key] = Tools::getValue($key, $value);
            }
        }

        // Translate messages
        foreach ($settings as $key => $value) {
            if (preg_match('/_message/', $key)) {
                $settings[$key] = $this->l($value);
            }
        }

        return $settings;
    }

    /**
     * @return array settings
     */
    private function getSettings()
    {
        return Tools::jsonDecode(Configuration::get(Tools::strtoupper($this->name)), true);
    }

    /**
     * @return array statuses
     */
    private function getStatuses($language_id = false)
    {
        if (!$language_id) {
            $language_id = (int) $this->context->language->id;
        }

        return OrderState::getOrderStates($language_id);
    }

    /*
     * hookNewOrder
     *
     * Triggered when a new order is placed.
     **/
    public function hookNewOrder($params)
    {
        $settings = $this->getSettings();
        if (!empty($settings)) {
            if ($settings['admin_enabled'] && !empty($settings['admin_phone'])) {
                $message = $this->tokenReplace(
                    $settings['admin_message'],
                    $params['customer'],
                    $params['order'],
                    $params['currency'],
                    $params['orderStatus']->id
                );
                $numbers = explode(', ', $settings['admin_phone']);
                foreach ($numbers as $number) {
                    $this->sendSms(
                        $number,
                        $message,
                        $settings['callr_username'],
                        $settings['callr_password'],
                        $settings['callr_sender'],
                        $settings['callr_debug']
                    );
                }
            }
        }
    }

    /**
     * hookActionOrderStatusUpdate.
     *
     * Order's status update event
     * Launch modules when the order's status of an order change.
     **/
    public function hookActionOrderStatusUpdate($params)
    {
        $status = $params['newOrderStatus']->id;
        $customer = new Customer((int) $params['cart']->id_customer);
        $delivery = new Address((int) ($params['cart']->id_address_delivery));
        $phone = !empty($delivery->phone_mobile) ? $delivery->phone_mobile : $delivery->phone;
        $country = new Country((int) $delivery->id_country);
        $country_code = $country->iso_code;
        $settings = $this->getSettings();
        if (!empty($settings)) {
            if ($settings['customer_notification_'.$status]) {
                $message = false;
                if ($settings['customer_message_'.$status] != '') {
                    $message = $settings['customer_message_'.$status];
                } elseif ($settings['customer_message_default'] != '') {
                    $message = $settings['customer_message_default'];
                }
                if ($message) {
                    $customer = new Customer((int) $params['cart']->id_customer);
                    $order = new Order((int) $params['id_order']);
                    $currency = new Currency((int) $params['cart']->id_currency);
                    $message = $this->tokenReplace($message, $customer, $order, $currency, $status);
                    $phoneformat = \libphonenumber\PhoneNumberUtil::getInstance();
                    try {
                        $proto = $phoneformat->parse($phone, $country_code);
                        $e164 = $phoneformat->format($proto, \libphonenumber\PhoneNumberFormat::E164);
                    } catch (Exception $e) {
                        if ($settings['callr_debug']) {
                            Logger::addLog('Invalid phone number: '.$phone.' ('.$country_code.') :'.$e->getMessage());
                        }

                        return;
                    }
                    $this->sendSms(
                        $e164,
                        $message,
                        $settings['callr_username'],
                        $settings['callr_password'],
                        $settings['callr_sender'],
                        $settings['callr_debug']
                    );
                }
            }
        }
    }

    /**
     * Replace tokens in SMS message.
     */
    private function tokenReplace($message, $customer, $order, $currency, $status_id)
    {
        // Translate the status
        $status_name = '';
        $statuses = $this->getStatuses($customer->id_lang);
        foreach ($statuses as $status) {
            if ($status['id_order_state'] == $status_id) {
                $status_name = $status['name'];
                break;
            }
        }
        // Replace
        $replacements = array(
            '[first_name]' => $customer->firstname,
            '[last_name]' => $customer->lastname,
            '[shop_name]' => $this->context->shop->name,
            '[order_id]' => $order->reference,
            '[order_amount]' => number_format(round($order->total_paid, 2), 2),
            '[order_status]' => $status_name,
            '[order_currency]' => $currency->sign,
        );
        $message = str_replace(array_keys($replacements), $replacements, $message);

        return $message;
    }

    /**
     * Send SMS.
     */
    private function sendSms($number, $message, $username, $password, $sender, $debug = false)
    {
        $api = new \CALLR\API\Client();
        $api->setAuthCredentials($username, $password);
        try {
            $api->call('sms.send', array($sender, $number, $message, null));
        } catch (Exception $e) {
            if ($debug) {
                Logger::addLog('Could not send SMS: '.$e->getMessage());
            }

            return false;
        }

        return true;
    }

    /**
     * Ajax SMS Tester.
     */
    public function ajaxProcessSmsTester()
    {
        if ($this->sendSms(
            Tools::getValue('number'),
            Tools::getValue('message'),
            Tools::getValue('username'),
            Tools::getValue('password'),
            Tools::getValue('sender'),
            Tools::getValue('debug')
        )) {
            echo '1';
        } else {
            echo '0';
        }
        die();
    }
}
