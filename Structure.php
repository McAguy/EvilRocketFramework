<?php

    /**
     * @author BreathLess
     * @date 29.10.10
     * @time 16:47
     */

    class Evil_Structure
    {
        protected static $_Structures = array();
        
        private static function _getMapper($type)
        {
            $config = Zend_Registry::get('config');

            if (!isset($config['evil']['object']['map'][$type]))
                return 'Fixed';
            else
                return $config['evil']['object']['map'][$type];
        }

        public static function getObject ($type, $id = null)
        {
            if (!isset(self::$_Structures['Object'][$type][$id]))
            {
                $className = 'Evil_Object_'.self::_getMapper($type);
                self::$_Structures['Object'][$type][$id] = new $className($type, $id);
            }
            return self::$_Structures['Object'][$type][$id];

        }

        public static function getComposite ($type, $id = null)
        {
            $className = 'Evil_Composite_'.self::_getMapper($type);
            return new $className($type, $id);
        }
    }