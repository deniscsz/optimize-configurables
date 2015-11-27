<?php
class Resultate_OptimizeConfigurables_Model_Catalog_Product_Type_Configurable extends Mage_Catalog_Model_Product_Type_Configurable
{
    /**
     * Retrieve Selected Attributes info
     *
     * @param  Mage_Catalog_Model_Product $product
     * @return array
     */
    public function getSelectedAttributesInfo($product = null)
    {
        $attributes = array();
        Varien_Profiler::start('CONFIGURABLE:'.__METHOD__);
        if ($attributesOption = $this->getProduct($product)->getCustomOption('attributes')) {
            $data = unserialize($attributesOption->getValue());
            $this->getUsedProductAttributeIds($product);
    
            $usedAttributes = $this->getProduct($product)->getData($this->_usedAttributes);
    
            foreach ($data as $attributeId => $attributeValue) {
                if (isset($usedAttributes[$attributeId])) {
                    $attribute = $usedAttributes[$attributeId];
                    $label = $attribute->getLabel();
                    $value = $attribute->getProductAttribute();
                    if ($value->getSourceModel()) {
                        // $value = $value->getSource()->getOptionText($attributeValue);
                        if (!Mage::app()->getStore()->isAdmin()) {
                            $value = $value->getSource()->getNeededOptionText($attributeValue);
                        } else {
                            $value = $value->getSource()->getOptionText($attributeValue);
                        }
                    }
                    else {
                        $value = '';
                    }
    
                    $attributes[] = array('label'=>$label, 'value'=>$value);
                }
            }
        }
        Varien_Profiler::stop('CONFIGURABLE:'.__METHOD__);
        return $attributes;
    }
    
    public function isSalable($product = null)
    {
        $salable = parent::isSalable($product);
    
        if ($salable !== false) {
            $salable = false;
            if (!is_null($product)) {
                $this->setStoreFilter($product->getStoreId(), $product);
            }
    
            if (!Mage::app()->getStore()->isAdmin() && $product) {
                $collection = $this->getUsedProductCollection($product)
                ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
                ->setPageSize(1)
                ;
                if ($collection->getFirstItem()->getId()) {
                    $salable = true;
                }
            } else {
                foreach ($this->getUsedProducts(null, $product) as $child) {
                    if ($child->isSalable()) {
                        $salable = true;
                        break;
                    }
                }
            }
        }
    
        return $salable;
    }
    
    public function getUsedProducts($requiredAttributeIds = null, $product = null)
    {
        Varien_Profiler::start('CONFIGURABLE:'.__METHOD__);
        if (!$this->getProduct($product)->hasData($this->_usedProducts)) {
            if (is_null($requiredAttributeIds)
            and is_null($this->getProduct($product)->getData($this->_configurableAttributes))) {
                // If used products load before attributes, we will load attributes.
                $this->getConfigurableAttributes($product);
                // After attributes loading products loaded too.
                Varien_Profiler::stop('CONFIGURABLE:'.__METHOD__);
                return $this->getProduct($product)->getData($this->_usedProducts);
            }
            
            $use_cache = false;
            if (!Mage::app()->getStore()->isAdmin() && $product) {
                if (!is_dir(Mage::getBaseDir('cache') . DS . 'associated')) {
                    @mkdir(Mage::getBaseDir('cache') . DS . 'associated', 0777);
                }
                
                $file_cache = Mage::getBaseDir('cache') . DS . 'associated' . DS . $product->getId() . '.php';
                $use_cache = true;
            }
            
            $usedProducts = array();
            if ($use_cache && is_file($file_cache)) {
                $data = include_once($file_cache);
                foreach ($data as $k => $d) {
                    $class_name = Mage::getConfig()->getModelClassName('catalog/product');
                    $usedProducts[] = new $class_name($d);
                }
            } else {
                $collection = $this->getUsedProductCollection($product)
                ->addAttributeToSelect('*')
                ->addFilterByRequiredOptions();
                
                if (is_array($requiredAttributeIds)) {
                    foreach ($requiredAttributeIds as $attributeId) {
                        $attribute = $this->getAttributeById($attributeId, $product);
                        if (!is_null($attribute))
                            $collection->addAttributeToFilter($attribute->getAttributeCode(), array('notnull'=>1));
                    }
                }
                
                if ($use_cache) {
                    $cache_str = '';
                    foreach ($collection as $id => $item) {
                        $item->unsetData('stock_item');
                        $cache_str .= $id . " => " . var_export($item->getData(), true) . ",\n";
                        $usedProducts[] = $item;
                    }
                    
                    $fd = fopen($file_cache, 'wb+');
                    fwrite($fd, "<?php\nreturn array(" . $cache_str . ");\n?>");
                    fclose($fd);
                } else {
                    foreach ($collection as $item) {
                        $usedProducts[] = $item;
                    }
                }
            }
            
            $this->getProduct($product)->setData($this->_usedProducts, $usedProducts);
        }
        Varien_Profiler::stop('CONFIGURABLE:'.__METHOD__);
        return $this->getProduct($product)->getData($this->_usedProducts);
    }
}
		