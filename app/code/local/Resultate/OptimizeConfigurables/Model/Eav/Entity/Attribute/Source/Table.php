<?php
class Resultate_OptimizeConfigurables_Model_Eav_Entity_Attribute_Source_Table extends Mage_Eav_Model_Entity_Attribute_Source_Table
{
    public function getNeededOptions($ids)
    {
        $storeId = $this->getAttribute()->getStoreId();
        $collection = Mage::getResourceModel('eav/entity_attribute_option_collection')
        ->setPositionOrder('asc')
        ->setAttributeFilter($this->getAttribute()->getId())
        ->addFieldToFilter('main_table.option_id', array('in' => $ids))
        ->setStoreFilter($this->getAttribute()->getStoreId())
        ->load();
        return $collection->toOptionArray();
    }
    
    /**
     * Get a text for option value
     *
     * @param string|integer $value
     * @return string
     */
    public function getNeededOptionText($value)
    {
        $isMultiple = false;
        if (strpos($value, ',')) {
            $isMultiple = true;
            $value = explode(',', $value);
        }
    
        $options = $this->getNeededOptions($value);
    
        if ($isMultiple) {
            $values = array();
            foreach ($options as $item) {
                if (in_array($item['value'], $value)) {
                    $values[] = $item['label'];
                }
            }
            return $values;
        }
    
        foreach ($options as $item) {
            if ($item['value'] == $value) {
                return $item['label'];
            }
        }
        return false;
    }
}
		