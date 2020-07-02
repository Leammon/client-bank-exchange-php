<?php

namespace Kily\Tools1C\ClientBankExchange;

class Parser implements \ArrayAccess
{
    protected $encoding;
    protected $result;

    public function __construct($file, $encoding = '')
    {
        $this->encoding = $encoding;
        $this->result = $this->parse_file($file);
    }

    /*public function detectEncoding($filePath){
        $fopen=fopen($filePath,'r');

        $row = fgets($fopen);
        $encodings = mb_list_encodings();
        $encoding = mb_detect_encoding( $row, mb_list_encodings() );

        if($encoding !== false) {
            $key = array_search($encoding, $encodings) !== false;
            if ($key !== false)
                unset($encodings[$key]);
            $encodings = array_values($encodings);
        }

        $encKey = 0;
        while ($row = fgets($fopen)) {
            if($encoding == false){
                $encoding = $encodings[$encKey++];
            }

            if(!mb_check_encoding($row, $encoding)){
                $encoding =false;
                rewind($fopen);
            }

        }

        return $encoding;

        //check string strict for encoding out of list of supported encodings
        $enc = mb_detect_encoding($str, mb_list_encodings(), true);

        if ($enc===false){
            //could not detect encoding
        }
        else if ($enc!=="UTF-8"){
            $str = mb_convert_encoding($str, "UTF-8", $enc);
        }
        else {
            //UTF-8 detected
        }
    }*/

    protected function defaultResult()
    {
        return [
            'general' => [],
            'filter' => [],
            'remainings' => [],
            'documents' => [],
        ];
    }

    public function parse_file($path)
    {
        if (!file_exists($path)) {
            throw new Exception('File does not exists: '.$path);
        }
        $text = file_get_contents($path);
        if(!$this->encoding){
            $str = '';
            $fopen=fopen($path,'r');
            while (($row = fgets($fopen)) && (strlen($str)<600)) {
                $str .=$row;
            }
            $detector = new EncodingDetector();
            $encoding = $detector->getEncoding($str);
            switch($encoding){
                case EncodingDetector::IBM866:
                    $text = iconv('CP866','UTF-8',$text);
                    break;
                case EncodingDetector::WINDOWS_1251:
                    $text = iconv('CP1251','UTF-8',$text);
                    break;
                case EncodingDetector::UTF_8:
                default:
                    //its ok
            }
        }
        return $this->parse($text);
    }

    public function parse($content)
    {
        if ($this->encoding) {
            $content = iconv($this->encoding, 'UTF-8', $content);
        }

        $result = $this->defaultResult();

        $header = '1CClientBankExchange';
        if ($header == substr($content, 0, strlen($header))) {
            $result['general'] = $this->general($content);
            $result['filter'] = $this->filter($content);
            $result['remainings'] = $this->remainings($content);
            $result['documents'] = $this->documents($content);
        } else {
            throw new Exception('Wrong format: 1CClientBankExchange not found');
        }

        return $result;
    }

    public function general($content)
    {
        $result = [];
        foreach (Model\GeneralSection::fields() as $key) {
            if (preg_match("/^{$key}=(.+)/um", $content, $matches)) {
                $result[$key] = trim($matches[1]);
            } else {
                $result[$key] = null;
            }
        }

        return new Model\GeneralSection($result);
    }

    public function filter($content)
    {
        $result = [];
        foreach (Model\FilterSection::fields() as $key) {
            $is_array = in_array($key,Model\FilterSection::arrayFields());
            if($is_array){
                $matchResult = preg_match_all("/^{$key}=(.*)$/um", $content, $matches);
            }
            else
                $matchResult = preg_match("/^{$key}=(.*)$/um", $content, $matches);

            if ($matchResult ) {
                if($is_array)
                    $matches[1] = array_unique($matches[1]);
                $result[$key] = !$is_array?trim($matches[1]):(count($matches[1])>1?$matches[1]:trim($matches[1][0]));
            } else {
                $result[$key] = null;
            }
        }
        return new Model\FilterSection($result);
    }

    public function remainings($content)
    {
        $result = [];

        if (preg_match('/СекцияРасчСчет([\s\S]*?)\sКонецРасчСчет/um', $content, $matches)) {
            $part = $matches[1];
            foreach (array_filter(preg_split('/\r?\n/um', $part)) as $line) {
                list($key, $val) = explode('=', trim($line), 2);
                $result[$key] = $val;
            }
        }

        return new Model\RemainingsSection($result);
    }

    public function documents($content)
    {
        $result = [];

        if (preg_match_all('/СекцияДокумент=(.*)\s([\s\S]*?)\sКонецДокумента/um', $content, $matches)) {
            foreach ($matches[0] as $match) {
                $doc = [];
                $part = $match;
                foreach (array_filter(preg_split('/\r?\n/um', $part)) as $line) {
                    @list($key, $val) = explode('=', trim($line), 2);
                    $doc[$key] = $val;
                }

                $type = isset($doc['СекцияДокумент']) ? $doc['СекцияДокумент'] : null;
                unset($doc['СекцияДокумент']);
                unset($doc['КонецДокумента']);

                $result[] = new Model\DocumentSection($type, $doc);
            }
        }

        return $result;
    }

    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->result[] = $value;
        } else {
            $this->result[$offset] = $value;
        }
    }

    public function offsetExists($offset)
    {
        return isset($this->result[$offset]);
    }

    public function offsetUnset($offset)
    {
        unset($this->result[$offset]);
    }

    public function offsetGet($offset)
    {
        return isset($this->result[$offset]) ? $this->result[$offset] : null;
    }

    public function __get($name)
    {
        if (isset($this->result[$name])) {
            return $this->result[$name];
        }

        $trace = debug_backtrace();
        trigger_error(
            'Undefined property via __get(): '.$name.
            ' in '.$trace[0]['file'].
            ' on line '.$trace[0]['line'],
            E_USER_NOTICE);

        return;
    }

    public function __toString() {
        $out = '';
        foreach(array_merge([new Model\StartSection()],$this->result) as $item) {
            if(is_array($item)) {
                foreach($item as $_item) {
                    $out .= $_item->__toString();
                }
            } else {
                $out .= $item->__toString();
            }
        }
        return iconv('UTF-8',$this->encoding,$out."КонецФайла\n");
    }
}
