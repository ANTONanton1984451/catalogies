<?php


namespace parsing\platforms\zoon;


use parsing\platforms\Filter;
use phpQuery;


class ZoonFilter extends Filter {
    const FORMAT_SIMPLE     = 0;
    const FORMAT_HARD       = 1;
    const FORMAT_MIX        = 2;

    private $format;
    private $result = [];

    public function clearData($data) {
        $this->checkFormat($data);

        $data = json_decode($data);

        if ($this->format == self::FORMAT_SIMPLE) {
            $this->clearSimpleData($data);
        }

        if ($this->format == self::FORMAT_HARD) {
            $this->clearHardData($data);
        }

        if ($this->format == self::FORMAT_MIX) {
            $this->clearMixData($data);
        }

        /*
        $doc = phpQuery::newDocument($clearData);
        $this->text = $doc->find('.js-comment-short-text.comment-text')->text();
        */
    }

    private function checkFormat($data)
    {
        $data = json_decode($data);
        $doc = phpQuery::newDocument($data->list);

        if ($doc->find('.comment-container.js-comment-container')->text() === ''){
            $this->format = self::FORMAT_HARD;
        } elseif ($doc->find('script')->text() === ''){
            $this->format = self::FORMAT_SIMPLE;
        } else {
            $this->format = self::FORMAT_MIX;
        }

        $doc->unloadDocument();
    }

    private function clearHardData($data)
    {
        $doc = phpQuery::newDocument($data->list);
        $buffer = $doc->find('script')->text();

        $buffer = explode('"', $buffer);

        for ($i = 1; $i < count($buffer); $i += 2){
            $temp = $buffer[$i];

            $temp = str_replace("A", "@", $temp);
            $temp = str_replace("=", "A", $temp);
            $temp = str_replace("@", "=", $temp);

            $temp = base64_decode($temp);


            $this->result[] = $temp;
        }

        $doc = phpQuery::unloadDocuments();

        $this->clearSimpleData($data);

        // todo: После разархивации, из hard формата, в дальнейшем данные можно обработать
        //          той же функцией, которая обрабатывает Simple Data

        // todo: $this->clearSimpleData();
    }

    private function clearMixData($data)
    {

    }

    private function clearSimpleData($data)
    {
        // todo: Удаление надписи "Показать" в Simple Data

        foreach ($this->result as $item) {
            //$doc_now = phpQuery::newDocument($item);
            exit();
            $doc_now->find();
        }
    }

    public function getNotifications()
    {
        // TODO: Implement getNotifications() method.
    }
}
