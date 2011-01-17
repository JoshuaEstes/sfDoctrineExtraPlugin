<?php

class Doctrine_Template_GoogleI18n extends Doctrine_Template_I18n
{
    /**
     * Array of GoogleI18n options
     *
     * @var string
     */
    protected $_options = array(
        'skipEmpty' =>  false,
    );

    protected
        $_url = 'http://ajax.googleapis.com/ajax/services/language/translate',
        $_apiVersion = '1.0';

    public function setTableDefinition()
    {
        $this->addListener(new Doctrine_Template_Listener_GoogleI18n($this));
    }

    public function getTranslation($from, $to, $string)
    {
        if (empty($string)) {
            return $string;
        }

        $langPair = $from . '|' . $to;
        $parameters = array(
            'v' => $this->_apiVersion,
            'q' => $string,
            'langpair' => $langPair
        );

        $url  = $this->_url . '?';

        foreach($parameters as $k => $p) {
            $url .= $k . '=' . urlencode($p) . '&';
        }

        $json = json_decode(file_get_contents($url));

        switch($json->responseStatus)
        {
            case 200:
                return $json->responseData->translatedText;
            break;

            default:
                throw new Exception("Unable to perform Translation:".$json->responseDetails);
        }
    }
}
