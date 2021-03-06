<?php
/*
 * @copyright 2019-2021 Dicr http://dicr.org
 * @author Igor A Tarasov <develop@dicr.org>
 * @license MIT
 * @version 19.04.21 17:02:13
 */

declare(strict_types = 1);

namespace dicr\http;

use DOMDocument;
use yii\base\BaseObject;
use yii\base\Exception;
use yii\httpclient\ParserInterface;
use yii\httpclient\Response;

use function preg_match;

use const LIBXML_DTDLOAD;
use const LIBXML_NOERROR;
use const LIBXML_NOWARNING;
use const LIBXML_PARSEHUGE;

/**
 * Парсер HTML-текста в \DOMDocument.
 */
class DOMDocumentParser extends BaseObject implements ParserInterface
{
    /** @var string формат ответа */
    public const FORMAT = 'dom-document';

    /**
     * Парсит контент.
     *
     * @param string $content HTML-контент
     * @param ?string $charset кодировка
     * @return DOMDocument
     * @throws Exception
     */
    public function parseContent(string $content, ?string $charset = null): DOMDocument
    {
        if ($charset === null) {
            $charset = 'UTF-8';
        }

        // если у документа тег с кодировкой стоит после того как utf-8 символы, то он не учитывается, поэтому добавляем насильно.
        // @link https://www.php.net/manual/en/domdocument.loadhtml.php
        $content =
            // объявление XML-документа. Здесь encoding не влияет на распознавание текста
            '<?xml version="1.0" encoding="' . $charset . '" standalone="yes"?>' .
            // meta старого типа (http-equiv) устанавливает кодировку текста. Она должны быть раньше чем текст в документе.
            '<meta http-equiv="Content-Type" content="text/html; charset=' . $charset . '"/>' .
            '<meta charset="' . $charset . '"/>' .
            $content;

        // создаем документ
        $doc = new DOMDocument('1.0', $charset);
        $doc->resolveExternals = false;
        $doc->recover = true;
        $doc->strictErrorChecking = false;
        $doc->validateOnParse = false;
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;
        $doc->substituteEntities = false;

        // пытаемся загрузить HTML
        libxml_clear_errors();
        if (! $doc->loadHTML($content, LIBXML_DTDLOAD | LIBXML_PARSEHUGE | LIBXML_NOWARNING |
            LIBXML_NOERROR | LIBXML_NOCDATA | LIBXML_NONET | LIBXML_NOXMLDECL)) {
            $err = libxml_get_last_error();
            throw new Exception($err ? $err->message . ' at ' . $err->file . ':' . $err->column :
                'Ошибка парсинга HTML');
        }

        $doc->normalizeDocument();

        return $doc;
    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function parse(Response $response): DOMDocument
    {
        // получаем кодировку ответа
        $contentType = $response->getHeaders()->get('content-type', '');
        $charset = preg_match('~charset=(.+)~i', $contentType, $matches) ? $matches[1] : 'UTF-8';

        return $this->parseContent($response->content, $charset);
    }
}
