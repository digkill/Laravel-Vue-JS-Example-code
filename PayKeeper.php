<?php

namespace App\Services\PayKeeper;

use App\Models\IntegrationPayKeeper;
use App\Models\PayKeeperModel;
use App\Services\PayKeeper\Exceptions\InvalidArgumentException;
use App\Services\PayKeeper\Models\Bill;
use App\Services\PayKeeper\Models\Payment;
use App\Services\PayKeeper\Models\PaymentRefund;
use App\Services\PayKeeper\Models\Setting;
use App\Services\PayKeeper\Models\User;
use Illuminate\Support\Collection;

/**
 * Class PayKeeper
 *
 */
class PayKeeper implements PayKeeperInterface
{
    /**
     * Api Url
     * @var string
     */
    private $_api_url;

    /**
     * 6. Токен безопасности
     * @var null|string
     */
    private $_token = null;

    /**
     * Поддомен PayKeeper
     * @var string
     */
    private $_subDomain;

    /**
     * Логин в личном кабинете PayKeeper
     * @var string
     */
    private $_login;

    /**
     * Соответствующий логину пароль
     * @var string
     */
    private $_password;

    /**
     * Секретное слово
     * @var string
     */
    private $_secret_seed;

    /**
     * Зашифрованое в base64 логин:пароль
     * @var string
     */
    private $_hash;

    /**
     * Заголовок для авторизации
     * @var array
     */
    private $_headers = [];

    /**
     * Http cod
     * @var int|null
     */
    private $_server_response_code = null;

    /**
     * @param IntegrationPayKeeper $model
     * @return PayKeeper
     * @throws InvalidArgumentException
     */
    public static function init(IntegrationPayKeeper $model)
    {
        return new self($model->subdomain, $model->credential);
    }

    /**
     * @param $subDomain
     * @param $login
     * @param $password
     * @return PayKeeper
     * @throws InvalidArgumentException
     */
    public static function initLogin($subDomain, $login, $password)
    {
        return new self($subDomain, null, $login, $password);
    }

    /**
     * PayKeeper constructor.
     * @param $subDomain
     * @param null $hash
     * @param null $login
     * @param null $password
     * @throws InvalidArgumentException
     */
    public function __construct($subDomain, $hash = null, $login = null, $password = null)
    {
        if (empty($subDomain)) {
            throw new InvalidArgumentException('Subdomain is empty');
        }

        if (!$hash && empty($login)) {
            throw new InvalidArgumentException('Login is empty');
        }

        if (!$hash && empty($password)) {
            throw new InvalidArgumentException('Password is empty');
        }

        $this->_subDomain = $subDomain;
        $this->_hash = $hash;
        $this->_login = $login;
        $this->_password = $password;
        $this->_api_url = "https://$subDomain.paykeeper.ru";

        $this->setAuthParameters();

    }

    /**
     * Создания заголовков авторизации
     *
     * @return void
     */
    public function setAuthParameters()
    {
        if (!$this->_hash) {
            $user = $this->_login;                                      # Логин в личном кабинете PayKeeper
            $password = $this->_password;                               # Соответствующий логину пароль
            $this->_hash = base64_encode("$user:$password");      # Формируем base64 хэш
        }
        $headers = [];
        array_push($headers,'Content-Type: application/x-www-form-urlencoded');
        array_push($headers,'Authorization: Basic '. $this->_hash);
        $this->_headers = $headers;
    }

    /**
     * 6.1. Получение токена безопасности
     *
     * @return mixed
     * @throws InvalidArgumentException
     */
    public function apiGetToken()
    {
        if ($this->_token) {
            return $this->_token;
        }

        $server_paykeeper = $this->_api_url;                        # укажите адрес вашего сервера PayKeeper

        # Готовим первый запрос на получение токена
        $uri="/info/settings/token/";                               # Запрос на получение токена

        $curl=curl_init();                                          # curl должен быть установлен

        curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($curl,CURLOPT_URL,$server_paykeeper.$uri);
        curl_setopt($curl,CURLOPT_CUSTOMREQUEST,'GET');
        curl_setopt($curl,CURLOPT_HTTPHEADER,$this->_headers);
        curl_setopt($curl,CURLOPT_HEADER,false);

        $out = curl_exec($curl);                                    # Инициируем запрос к API
        $php_array = json_decode($out,true);                    # Сохраняем результат в массив
        if (isset($php_array['token'])) {
            $this->_token = $php_array['token'];
            return $this->_token;
        }

        throw new InvalidArgumentException('Token is empty');

    }

    /**
     * 3.4. Подготовка счёта
     *
     * @param array $property - $property  = [
            'pay_amount' => 100,
            'clientid' => 'Стамакс Юрий',
            'orderid' => 'detki-146',
            'service_name' => 'Subscription - 8 dats',
            'client_email' => 'zyozia@i.ua',
            'client_phone' => '0950959595',
            'expiry' => '2019-06-01',
            'token' => $this->apiGetToken(),
            ];
     * @return Bill
     * @throws InvalidArgumentException
     */
    public function apiPostBill(array $property)
    {
        # Готовим к выполнению запрос Подготовку счёта (3.4.)

        if (!isset($property['pay_amount'])) {
            throw new InvalidArgumentException('pay_amount is empty');
        }
        if (!isset($property['clientid'])) {
            throw new InvalidArgumentException('clientid is empty');
        }
        if (!isset($property['orderid'])) {
            throw new InvalidArgumentException('orderid is empty');
        }
        if (!isset($property['service_name'])) {
            throw new InvalidArgumentException('orderid is empty');
        }
        if (!isset($property['client_email'])) {
            //throw new InvalidArgumentException('client_email is empty');
        }
        if (!isset($property['client_phone'])) {
            //throw new InvalidArgumentException('client_phone is empty');
        }
        if (!isset($property['expiry'])) {
            throw new InvalidArgumentException('orderid is empty');
        }
        $property['token'] = $this->apiGetToken();

        $data = '';
        foreach ($property as $key=>$value)
        {
            $value = trim($value);
            if ($key === 'clientid') {
                if (mb_strlen($value) === 0) {
                    $value = 'Client';
                }
            }
            $data .= "$key=$value&";
        }

        $server_paykeeper = $this->_api_url;                        # укажите адрес вашего сервера PayKeeper
        $uri="/change/invoice/preview/";                            # Запрос 3.4 JSON API
        $curl=curl_init();                                          # curl должен быть установлен
        curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($curl,CURLOPT_URL,$server_paykeeper.$uri);
        curl_setopt($curl,CURLOPT_CUSTOMREQUEST,'POST');
        curl_setopt($curl,CURLOPT_HTTPHEADER,$this->_headers);
        curl_setopt($curl,CURLOPT_HEADER,false);
        curl_setopt($curl,CURLOPT_POSTFIELDS,$data);

        $out = curl_exec($curl);                                    # Инициируем запрос к API
        $this->_server_response_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $php_array = json_decode($out,true);                    # Сохраняем результат в массив

        if ($out === false) {
            throw new InvalidArgumentException('Проверте креды PayKeeper-а');
        } elseif ($this->_server_response_code === 200) {
            if (isset($php_array['invoice_id'])) {
                $php_array['result'] = 'success';
                return new Bill($php_array);
            } elseif (isset($php_array['result']) && $php_array['result'] == 'fail') {
                throw new InvalidArgumentException($php_array['msg']);
            }
        } elseif ($this->_server_response_code === 401) {
            throw new InvalidArgumentException('Пользователь не авторизирован');
        } elseif ($this->_server_response_code === 303) {
            throw new InvalidArgumentException('Проверте креды PayKeeper-а');
        }


        throw new InvalidArgumentException('Error');
    }

    /**
     * 3.5. Отправка счёта клиенту
     *
     * @param int $invoiceId
     * @return Bill
     * @throws InvalidArgumentException
     */
    public function apiPostSendBill(int $invoiceId)
    {
        # Готовим к выполнению запрос Отправка счёта клиенту (3.5.)

        $server_paykeeper = $this->_api_url;                        # укажите адрес вашего сервера PayKeeper
        $uri="/change/invoice/send/";                              # Запрос 3.4 JSON API
        $curl=curl_init();                                          # curl должен быть установлен
        curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($curl,CURLOPT_URL,$server_paykeeper.$uri);
        curl_setopt($curl,CURLOPT_CUSTOMREQUEST,'POST');
        curl_setopt($curl,CURLOPT_HTTPHEADER,$this->_headers);
        curl_setopt($curl,CURLOPT_HEADER,false);
        curl_setopt($curl,CURLOPT_POSTFIELDS, "id=$invoiceId&token={$this->apiGetToken()}");

        $out = curl_exec($curl);                                    # Инициируем запрос к API
        $this->_server_response_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $php_array = json_decode($out,true);                    # Сохраняем результат в массив

        if ($out === false) {
            throw new InvalidArgumentException('Проверте креды PayKeeper-а');
        } elseif ($this->_server_response_code === 200) {
            if (isset($php_array['result']) && $php_array['result'] == 'success') {
                return new Bill($php_array);
            } elseif (isset($php_array['result']) && $php_array['result'] == 'fail') {
                throw new InvalidArgumentException($php_array['msg']);
            }
        } elseif ($this->_server_response_code === 401) {
            throw new InvalidArgumentException('Пользователь не авторизирован');
        } elseif ($this->_server_response_code === 303) {
            throw new InvalidArgumentException('Проверте креды PayKeeper-а');
        }


        throw new InvalidArgumentException('Token is empty');
    }

    /**
     * 3.1. Запрос получения данных счёта
     *
     * @param int $invoiceId
     * @return Bill
     * @throws InvalidArgumentException
     */
    public function apiGetBill(int $invoiceId)
    {
        $server_paykeeper = $this->_api_url;                    # укажите адрес вашего сервера PayKeeper

        # Готовим первый запрос на получение токена
        $uri = "/info/invoice/byid/?id=$invoiceId";             # Запрос на получение токена

        $curl=curl_init();                                      # curl должен быть установлен

        curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($curl,CURLOPT_URL,$server_paykeeper.$uri);
        curl_setopt($curl,CURLOPT_CUSTOMREQUEST,'GET');
        curl_setopt($curl,CURLOPT_HTTPHEADER,$this->_headers);
        curl_setopt($curl,CURLOPT_HEADER,false);

        $out=curl_exec($curl);                                  # Инициируем запрос к API
        $php_array=json_decode($out,true);                  # Сохраняем результат в массив

        if (is_array($php_array)) {
            return new Bill($php_array);
        }

        throw new InvalidArgumentException('Error');
    }

    /**
     * 2.3. Запрос получения информации о платеже по идентификатору
     *
     * @param int $paymentId
     * @return Payment
     * @throws InvalidArgumentException
     */
    public function apiGetPayment(int $paymentId)
    {
        $server_paykeeper = $this->_api_url;                    # укажите адрес вашего сервера PayKeeper

        # Готовим первый запрос на получение токена
        $uri = "/info/payments/byid/?id=$paymentId";            # Запрос на получение токена

        $curl=curl_init();                                      # curl должен быть установлен

        curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($curl,CURLOPT_URL,$server_paykeeper.$uri);
        curl_setopt($curl,CURLOPT_CUSTOMREQUEST,'GET');
        curl_setopt($curl,CURLOPT_HTTPHEADER,$this->_headers);
        curl_setopt($curl,CURLOPT_HEADER,false);

        $out=curl_exec($curl);                                  # Инициируем запрос к API
        $this->_server_response_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $php_array=json_decode($out,true);                  # Сохраняем результат в массив

        if ($this->_server_response_code === 200) {
            if (is_array($php_array)) {
                return new Payment(collect($php_array)->first());
            } else {
                return new Payment([
                    'result' => 'fail',
                    'msg' => 'Не верный id'
                ]);
            }

        }
        throw new InvalidArgumentException('Error');
    }

    /**
     * 2.7. Запрос информации по возвратам для платежа
     *
     * @param int $paymentId
     * @return Collection
     * @throws InvalidArgumentException
     */
    public function apiGetPaymentRefund(int $paymentId)
    {
        $server_paykeeper = $this->_api_url;                    # укажите адрес вашего сервера PayKeeper

        # Готовим первый запрос на получение токена
        $uri = "/info/refunds/bypaymentid/?id=$paymentId";      # Запрос на получение токена

        $curl=curl_init();                                      # curl должен быть установлен

        curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($curl,CURLOPT_URL,$server_paykeeper.$uri);
        curl_setopt($curl,CURLOPT_CUSTOMREQUEST,'GET');
        curl_setopt($curl,CURLOPT_HTTPHEADER,$this->_headers);
        curl_setopt($curl,CURLOPT_HEADER,false);

        $out=curl_exec($curl);                                  # Инициируем запрос к API
        $this->_server_response_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $php_array=json_decode($out,true);                  # Сохраняем результат в массив

        if ($this->_server_response_code === 200) {
            if (is_array($php_array)) {
                $result = [];
                foreach ($php_array as $item) {
                    $result[] = new PaymentRefund($item);
                }
                return collect($result);
            } else {
                return collect();
            }

        }
        throw new InvalidArgumentException('Error');
    }

    /**
     * 4.3. Запрос настроек
     *
     * @return Setting|array
     * @throws InvalidArgumentException
     */
    public function apiGetSetting()
    {
        $server_paykeeper = $this->_api_url;                    # укажите адрес вашего сервера PayKeeper

        # Готовим первый запрос на получение токена
        $uri = "/info/organization/settings/";                  # Запрос на получение токена

        $curl=curl_init();                                      # curl должен быть установлен

        curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($curl,CURLOPT_URL,$server_paykeeper.$uri);
        curl_setopt($curl,CURLOPT_CUSTOMREQUEST,'GET');
        curl_setopt($curl,CURLOPT_HTTPHEADER,$this->_headers);
        curl_setopt($curl,CURLOPT_HEADER,false);

        $out=curl_exec($curl);                                  # Инициируем запрос к API
        $this->_server_response_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $php_array=json_decode($out,true);                  # Сохраняем результат в массив

        if ($out === false) {
            throw new InvalidArgumentException('Проверте креды PayKeeper-а');
        }
        if ($this->_server_response_code === 200) {
            return new Setting($php_array);
        } elseif ($this->_server_response_code === 401) {
            return [
                'result' => 'fail',
                'msg' => 'Пользователь не авторизирован'
            ];
        } elseif ($this->_server_response_code === 303) {
            throw new InvalidArgumentException('Проверте креды PayKeeper-а');
        }
        throw new InvalidArgumentException('Error');
    }

    /**
     * 4.1. Запрос настроек текущего пользователя
     *
     * @return User|array
     * @throws InvalidArgumentException
     */
    public function apiGetUserSetting()
    {
        $server_paykeeper = $this->_api_url;                    # укажите адрес вашего сервера PayKeeper

        # Готовим первый запрос на получение токена
        $uri = "/info/user/settings/";                          # Запрос на получение токена

        $curl=curl_init();                                      # curl должен быть установлен

        curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($curl,CURLOPT_URL,$server_paykeeper.$uri);
        curl_setopt($curl,CURLOPT_CUSTOMREQUEST,'GET');
        curl_setopt($curl,CURLOPT_HTTPHEADER,$this->_headers);
        curl_setopt($curl,CURLOPT_HEADER,false);

        $out=curl_exec($curl);                                  # Инициируем запрос к API
        $this->_server_response_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $php_array=json_decode($out,true);                  # Сохраняем результат в массив

        if ($this->_server_response_code === 200) {
            return new User($php_array);
        } elseif ($this->_server_response_code === 401) {
            return [
                'result' => 'fail',
                'msg' => 'Пользователь не авторизирован'
            ];
        }
        throw new InvalidArgumentException('Error');
    }

    /**
     * 5.1. Запрос на изменение параметра
     *
     * @param string $name
     * @param $value
     * @return array|mixed
     * @throws InvalidArgumentException
     */
    public function apiPostSettingChange(string $name, string $value)
    {
        # Готовим к выполнению запрос
        $parameters = [
            'informer_type',    // - Режим работы информера, принимает значение post или email
            'informer_url',     // - URL уведомления для информера по которому отправляется информация о принятом платеже
            'informer_seed',    // - Секретное слово для подписи сообщений информера
            'success_url',      // - URL для возврата в случае успешной оплаты
            'fail_url',         // - URL для возврата в случае ошибки при оплате
        ];

        if (!in_array($name, $parameters)) {
            throw new InvalidArgumentException('Invalid parameter');
        }

        if ($name == 'informer_type') {
            if (!in_array($value, ['post', 'email'])) {
                throw new InvalidArgumentException('Parameter informer_type past be post or email');
            }
        }
        $property['name'] = $name;
        $property['value'] = $value;
        $property['token'] = $this->apiGetToken();

        $data = '';
        foreach ($property as $key=>$value)
        {
            $data .= "$key=$value&";
        }

        $server_paykeeper = $this->_api_url;                        # укажите адрес вашего сервера PayKeeper
        $uri="/change/organization/setting/";                       # Запрос JSON API
        $curl=curl_init();                                          # curl должен быть установлен
        curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($curl,CURLOPT_URL,$server_paykeeper.$uri);
        curl_setopt($curl,CURLOPT_CUSTOMREQUEST,'POST');
        curl_setopt($curl,CURLOPT_HTTPHEADER,$this->_headers);
        curl_setopt($curl,CURLOPT_HEADER,false);
        curl_setopt($curl,CURLOPT_POSTFIELDS,$data);

        $out = curl_exec($curl);                                    # Инициируем запрос к API
        $php_array = json_decode($out,true);                    # Сохраняем результат в массив

        if (isset($php_array['result'])) {

            if ($php_array['result'] == 'success') {
                return [
                    'result' => 'success',
                ];
            } elseif ($php_array['result'] == 'fail') {
                return [
                    'result' => 'fail',
                    'msg' => isset($php_array['msg']) ? $php_array['msg'] : 'Ошибка!'
                ];
            }
        }

        throw new InvalidArgumentException('Error');
    }

    /**
     * Приём POST оповещений
     * Подтверждение оплаты с помощю POST оповещений
     *
     * @param array $parameters
     * @param $secret_seed - Секретное слово
     * @return void
     */
    public function callback(array $parameters, string $secret_seed)
    {
        $id = $parameters['id'];
        $sum = $parameters['sum'];
        $clientid = $parameters['clientid'];
        $orderid = $parameters['orderid'];
        $key = $parameters['key'];

        if ($key != md5 ($id.number_format($sum, 2, ".", "").$clientid.$orderid.$secret_seed)) {
            echo "Error! Hash mismatch";
            exit;
        }

        echo "OK ".md5($id.$secret_seed);
    }

    /**
     * Return last api response http code
     * @return string|null
     */
    public function get_response_code()
    {
        return $this->_server_response_code;
    }
}
