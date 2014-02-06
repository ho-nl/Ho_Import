<?php
/**
 * Ho_Import
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * @category    Ho
 * @package     Ho_Import
 * @copyright   Copyright © 2013 H&O (http://www.h-o.nl/)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Paul Hachmang – H&O <info@h-o.nl>
 */
class Ho_Import_Model_Source_Adapter_Db extends Zend_Db_Table_Rowset
{

    /**
     * Constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        $logHelper = Mage::helper('ho_import/log');
        $model = $config['model'];
        $query = $config['query'];

        unset($config['model']);
        unset($config['query']);

        /** @var Zend_Db_Adapter_Abstract $db */
        $db = new $model($config);

        if (isset($config['limit']) || isset($condig['offset'])) {
            $limit  = (int) isset($config['limit']) ? $config['limit'] : 0;
            $offset = (int) isset($config['offset']) ? $config['offset'] : 0;
            $logHelper->log($logHelper->__('Setting limit to %s and offset to %s', $limit, $offset), Zend_Log::NOTICE);
            $query = $db->limit($query, $config['limit'], $offset);
            $logHelper->log($query, Zend_Log::DEBUG);
        }

        $logHelper->log('Fetching data...');
        $result = $db->fetchAll($query);
        $logHelper->log('Done');
        $config['data'] = &$result;

        return parent::__construct($config);
    }

    protected function _loadAndReturnRow($position)
    {
        if (!isset($this->_data[$position])) {
            #require_once 'Zend/Db/Table/Rowset/Exception.php';
            throw new Zend_Db_Table_Rowset_Exception("Data for provided position does not exist");
        }

        return $this->_data[$position];
    }

}