<?php

class ErrorController extends Zend_Controller_Action
{

    /**
     * Initialize controller
     *
     * @return void
     */
    public function init()
    {
        /**
         * Setup contexts
         */
        $contextSwitch = $this->_helper->ajaxContext;
        $contextSwitch->addActionContext('authentication-required', array('html', 'json'))
                      ->initContext();
    }

    /**
     * Error action
     *
     * Catch-all action for all unhandled exceptions thrown during dispatch
     *
     * @return void
     */
    public function errorAction()
    {
        $errors = $this->_getParam('error_handler');
        
        $this->view->exception = $errors->exception;
        $this->view->request   = $errors->request;
        
        // conditionally display exceptions
        $this->view->showExceptions = ($this->getInvokeArg('displayExceptions') == true);
        
        switch ($errors->type) {
            case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_CONTROLLER:
            case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ACTION:
                $this->_forward("not-found");
                break;
        
            default:
                if ($errors->exception instanceof Zend_Controller_Action_Exception) {
                    switch (true) {
                        case $errors->exception instanceof Bear_Controller_Action_Exception_NotAuthenticated:
                            $this->_forward('authentication-required');
                            break;
                            
                        case $errors->exception instanceof Bear_Controller_Action_Exception_NotAuthorized:
                            $this->_forward("forbidden");
                            break;
                            
                        case $errors->exception instanceof Bear_Controller_Action_Exception_ParameterMissing:
                            $this->_forward("not-found");
                            break;
                            
                        case $errors->exception instanceof Bear_Controller_Action_Exception_ResourceNotFound:
                            $this->_forward("not-found");
                            break;
                            
                        default:
                            $this->_forward("internal-server-error");
                            break;
                    }
                    
                } else {
                    $this->_forward("internal-server-error");
                }
                break;
        }
    }

    /**
     * Authentication Required Action
     *
     * @return void
     */
    public function authenticationRequiredAction()
    {
        // json context: set the message and return immediately    
        if ($this->_helper->ajaxContext->getCurrentContext() == 'json') {
            $this->view->success = false;
            $this->view->status  = 'error';
            $this->view->message = 'Your session has expired. Please login to continue.';
            
            return;
        }
        
        // set error message to flash messenger
        $this->_helper
             ->flashMessenger
             ->setNamespace('error')
             ->addMessage("You must be logged in to access that page");
        
        // default context
        if (is_null($this->_helper->ajaxContext->getCurrentContext())) {
            // save the current requested page in session
            // for post-login redirect
            $loginSession = new Zend_Session_Namespace('login');
            $loginSession->postLoginUrl = $this->getRequest()->getRequestUri();
            
            // redirect to login page
            $this->_helper
                 ->redirector
                 ->gotoRoute(
                     array(
                         'module'     => 'users',
                         'controller' => 'account',
                         'action'     => 'login',
                     )
                 );
        }
    }

    /**
     * Forbidden Action
     *
     * @return void
     */
    public function forbiddenAction()
    {
        $this->getResponse()
              ->setHttpResponseCode(403);
    }

    /**
     * File Not Found Action
     *
     * @return void
     */
    public function notFoundAction()
    {
        // Log notice, if logger available
        if ($log = $this->_getLog()) {
            $log->notice(
                "Page Not Found: " 
                . $_SERVER['REQUEST_URI'] 
                . ' - ' 
                . $this->_getParam('error_handler')->exception->getMessage()
            );
        }
        
        $this->getResponse()
             ->setHttpResponseCode(404);
    }

    /**
     * Internal Server Error Action
     *
     * @return void
     */
    public function internalServerErrorAction()
    {
        // Log exception, if logger available
        if ($log = $this->_getLog()) {
            $log->crit($this->_getParam('error_handler')->exception);
        }
        
        $this->getResponse()
             ->setHttpResponseCode(500);
    }

    /**
     * Get Log
     *
     * @return Zend_Log|false
     */
    protected function _getLog()
    {
        $bootstrap = $this->getInvokeArg('bootstrap');
        if (!$bootstrap->hasPluginResource('Log')) {
            return false;
        }
        $log = $bootstrap->getResource('Log');
        return $log;
    }


}

