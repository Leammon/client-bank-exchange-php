<?php

namespace Kily\Tools1C\ClientBankExchange;

/**
 * Class EncodingDetector
 */
class EncodingDetector
{
    const UTF_8 = 'utf-8';
    const WINDOWS_1251 = 'windows-1251';
    const KOI8_R = 'koi8-r';
    const IBM866 = 'ibm866';
    const ISO_8859_5 = 'iso-8859-5';
    const MAC_CYRILLIC = 'mac-cyrillic';

    /** @var array<string, array<string, string>> */
    protected $rangeModel = [
        'windows-1251' => [
            'upper' => '168,192-212,214-223',
            'lower' => '184,224-255',
        ],
        'koi8-r' => [
            'upper' => '179,224-231, 233-255',
            'lower' => '163,192-223',
        ],
        'iso-8859-5' => [
            'upper' => '161,176-196,198-207',
            'lower' => '208-239,241',
        ],
        'ibm866' => [
            'upper' => '128-148,150-159,240',
            'lower' => '160-175,224-239,241',
        ],
        'mac-cyrillic' => [
            'upper' => '128-148,150-159,221',
            'lower' => '222-254',
        ],
    ];

    /** @var array */
    protected $ranges;

    /**
     * EncodingDetector constructor.
     */
    public function __construct()
    {
        // default setting
        $this->enableEncoding(
            [
                $this::WINDOWS_1251,
                $this::IBM866,
                $this::UTF_8,
            ]
        );
    }

    /**
     * Method to enable encoding definition
     * Example:
     * $detector->enableEncoding([
     *      $detector::IBM866,
     *      $detector::MAC_CYRILLIC,
     * ]);
     *
     * @param array $encodingList
     */
    public function enableEncoding(array $encodingList)
    {
        foreach ($encodingList as $encoding) {
            if (isset($this->rangeModel[$encoding])) {
                $this->ranges[$encoding] = $this->getRanges($this->rangeModel[$encoding]);
            }
        }
    }

    /**
     * Method to disable encoding definition
     * Example:
     * $detector->disableEncoding([
     *      $detector::ISO_8859_5,
     * ]);
     *
     * @param array $encodingList
     */
    public function disableEncoding(array $encodingList)
    {
        foreach ($encodingList as $encoding) {
            unset($this->ranges[$encoding]);
        }
    }

    /**
     * Method for adding custom encoding
     * Example:
     * $detector->addEncoding([
     *      'encodingName' => [
     *          'upper' => '1-50,200-250,253', // uppercase character number range
     *          'lower' => '55-100,120-180,199', // lowercase character number range
     *      ],
     * ]);
     *
     * @param array $ranges
     */
    public function addEncoding(array $ranges)
    {
        foreach ($ranges as $encoding => $config) {
            if (isset($config['upper'], $config['lower'])) {
                $this->ranges[$encoding] = $this->getRanges($config);
            }
        }
    }

    /**
     * Method for converting text of an unknown encoding into a given encoding, by default in utf-8
     * optional parameters:
     * $extra = '//TRANSLIT' (default setting) , other options: '' or '//IGNORE'
     * $encoding = 'utf-8' (default setting) , other options: any encoding that is available iconv
     *
     * @param string $text
     * @param string $extra
     * @param string $encoding
     * @return false|string
     */
    public function iconvXtoEncoding(&$text, $extra = '//TRANSLIT', $encoding = EncodingDetector::UTF_8)
    {
        if (!in_array($extra, ['//TRANSLIT', '//IGNORE'])) {
            $extra = '//TRANSLIT';
        }
        $res = $text;
        $xec = $this->getEncoding($text);
        if ($xec !== $encoding) {
            $res = iconv($xec, $encoding, $text);
        }

        return $res;
    }

    /**
     * Definition of text encoding
     *
     * @param string $text
     * @return string
     */
    public function getEncoding(&$text)
    {
        $res = $this::UTF_8;
        if ($this->isUtf($text) == false) {
            $max = 0;
            $text = count_chars($text, 1);
            foreach ($this->ranges as $encoding => $config) {
                $upc = array_intersect_key($text, $config['upper']);
                $loc = array_intersect_key($text, $config['lower']);
                $sum = (array_sum($upc) + array_sum($loc) * 3);
                if ($sum > $max) {
                    $max = $sum;
                    $res = $encoding;
                }
            }
        }

        return $res;
    }

    /**
     * UTF Encoding Definition Method
     *
     * @param string $text
     * @return bool
     */
    private function isUtf(&$text)
    {
        return (bool)preg_match('/./u', $text);
    }

    /**
     * @param array $config
     * @return array
     */
    private function getRanges($config)
    {
        return [
            'upper' => $this->getRange($config['upper']),
            'lower' => $this->getRange($config['lower']),
        ];
    }

    /**
     * Method to convert a range from a string to an array
     *
     * @param string $str
     * @return array|null
     */
    private function getRange(&$str)
    {
        $res = [];
        foreach (explode(',', $str) as $item) {
            $arr = explode('-', $item);
            if (count($arr) > 1) {
                $arr = range($arr[0], $arr[1]);
            }
            $res = array_merge($res, $arr);
        }

        return array_flip($res);
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function getEncodingList()
    {
        return $this->ranges;
    }
}
