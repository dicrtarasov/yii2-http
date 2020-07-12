<?php
/**
 * @copyright 2019-2020 Dicr http://dicr.org
 * @author Igor A Tarasov <develop@dicr.org>
 * @license proprietary
 * @version 12.07.20 13:04:38
 */

declare(strict_types = 1);
namespace dicr\http;

use Yii;
use yii\base\Behavior;
use yii\base\Exception;
use yii\httpclient\Client;
use yii\httpclient\RequestEvent;

/**
 * HTTP-compression support for yii\httpclient\Client
 */
class HttpCompressionBehavior extends Behavior
{
    /**
     * @inheritdoc
     */
    public function events()
    {
        return [
            Client::EVENT_BEFORE_SEND => '_beforeSend',
            Client::EVENT_AFTER_SEND => '_afterSend'
        ];
    }

    /**
     * Adjust request.
     *
     * @param RequestEvent $event
     */
    public function _beforeSend(RequestEvent $event)
    {
        // add accept-encoding header
        $headers = $event->request->headers;
        if (! $headers->has('accept-encoding')) {
            $headers->set('accept-encoding', 'gzip, deflate, compress');
        }
    }

    /**
     * Adjust response.
     *
     * @param RequestEvent $event
     * @throws Exception
     * @noinspection PhpUsageOfSilenceOperatorInspection
     */
    public function _afterSend(RequestEvent $event)
    {
        $response = $event->response;
        $encoding = $response->headers->get('content-encoding');

        if ($encoding !== null) {
            $decoded = null;

            switch (strtolower($encoding)) {
                case 'deflate':
                    $decoded = @gzinflate($response->content);
                    break;

                case 'compress':
                    $decoded = @gzuncompress($response->content);
                    break;

                case 'gzip':
                    $decoded = @gzdecode($response->content);
                    break;

                default:
                    throw new Exception('unknown encode method: ' . $encoding);
            }

            if ($decoded !== false) {
                $response->content = $decoded;
                Yii::debug('Ответ декодирован из: ' . $encoding, __METHOD__);
            }
        }
    }
}
