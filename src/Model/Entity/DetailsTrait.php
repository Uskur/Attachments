<?php 
namespace Uskur\Attachments\Model\Entity;


trait DetailsTrait
{

    public function setDetail($detail, $value)
    {
        $json = json_decode($this->details,true);
        $json[$detail] = $value;
        $this->details = json_encode($json);
    }
    
    public function getDetail($detail)
    {
        $json = json_decode($this->details,true);
        if(!isset($json[$detail])) return null;
        return $json[$detail];
    }
    
    protected function _getDetailsArray()
    {
        return json_decode($this->details,true);
    }
}
