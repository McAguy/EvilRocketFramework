<?php
    /**
     * @author BreathLess
     * @name Evil_Access Plugin
     * @type Zend Plugin
     * @description: Access Engine for ZF
     * @package Evil
     * @subpackage Access
     * @version 0.2
     * @date 24.10.10
     * @time 14:20
     */

  class Evil_Access extends Zend_Controller_Plugin_Abstract
    {
        private static $_rules;
      
        const ACCESS_FILE 		= '/configs/access.json';
        const ACCESS_METHOD 	= 'ACCESS';

        // const ACCESS_FILE 		= '/configs/roles.json';
        // const ACCESS_METHOD 	= 'ROLE';        
        
        public function routeShutdown(Zend_Controller_Request_Abstract $request)
        {
            parent::routeStartup ($request);
            $this->init();
           
            if ($this->denied($request->getParam('id'),
                $request->getControllerName(), $request->getActionName()))
                    throw new Evil_Exception('Access Denied for '.$request->getControllerName().'::'.$request->getActionName(), 403);
        }

        public function init ()
        {
            self::$_rules = json_decode(file_get_contents(APPLICATION_PATH.self::ACCESS_FILE), true);
            return true;
        }

        private function _resolve($condition, $object, $subject)
        {
            if ('*' !== $condition)
                return self::$condition($object, $subject);
            else
                return true;
        }
        
        public function _check ($subject, $controller, $action)
        {
        	if (self::ACCESS_METHOD == 'ROLE')
        	{
        		return $this->_check_by_role_table($subject, $controller, $action);
        	}
        	else return $this->_check_by_access_table($subject, $controller, $action);
        }
        
        // by Artemy
        private function _check_by_role_table ($subject, $controller, $action)
        {
        	$decisions = array();
        	$object 	= Zend_Registry::get('userid');
        	$user 		= new Evil_Object_Fixed('user', $object);
        	$role 		= $user->getValue('role');
            $logger 	= Zend_Registry::get('logger');
            Zend_Wildfire_Plugin_FirePhp::group('Access');   
            
            // Роль для гостя - незарег. пользователя
            $role = $object == -1 ? 'guest' : $role;
            
            // По 3-м возможным вариантам
            foreach (array('all', $role, $object) as $__user_role)
            {
            	if (!isset(self::$_rules[$__user_role][$controller])) continue; else
            	$current = self::$_rules[$__user_role][$controller];
            	
	            if (is_array($current))
	            {
	            	if (empty($current))
	            	{
	            		// пустой массив - все методы - разрешаем
	            		$decision = true;
	            	}
	            	elseif (in_array($action, $current))
	            	{
	            		// есть в списке - разрешаем
	            		$decision = true;           		
	            	}
	            }        
            }    
            
            Zend_Wildfire_Plugin_FirePhp::groupEnd('Access');     	
            return $decision;
        }
        
        private function _check_by_access_table ($subject, $controller, $action)
        {
            $decisions = array();
            $object = Zend_Registry::get('userid');
            $user = new Evil_Object_Fixed('user', $object);
            $role = $user->getValue('role');
            $logger = Zend_Registry::get('logger');
            Zend_Wildfire_Plugin_FirePhp::group('Access');
            
            $conditions = array('controller', 'action', 'object', 'subject', 'role');
          
            foreach(self::$_rules as $ruleName => $rule)
            {
                $selected = true;
                foreach ($conditions as $condition)
                {
                    if (isset($rule[$condition]))
                    {                        
                        if (is_array($rule[$condition]))
                        {
                            if (!in_array($$condition, $rule[$condition]))
                            {
                                $selected = false;
                            	break;
                            }
                        }
                        elseif ($rule[$condition] != $$condition)
                        {
                            $selected = false;
                            break;
                        }
                    }
                }

                if ($selected)
                {
                    $decisions[(int) $rule['weight']] = $rule['decision'];
                    $logger->log($ruleName.' applicable!', Zend_Log::INFO);
                }
            }
            if (count($decisions)>0)
            {
                $decision = $decisions[max(array_keys($decisions))];
                $logger->info('Вердикт: '.$decision);
            } else
                throw new Exception('No rules applicable');

            Zend_Wildfire_Plugin_FirePhp::groupEnd('Access');
            return $decision;
        }

        public function allowed($subject, $controller, $action)
        {
            return self::_check($subject, $controller, $action);
        }

        public function denied($subject, $controller, $action)
        {           
            return !self::_check($subject, $controller, $action);
        }

        private function isOwner($subject)
        {
            return ($subject->owner() == Zend_Registry::get('userid'));
        }
    }