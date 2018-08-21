<?php

namespace JonoM\CustomErrors;

use Page;
use PageController;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Control\Session;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBHTMLText;

/**
 * Enhances error handling for a controller with themed output
 *
 * Precedence: error messages passed in $controller->httpError() will only be used if no yml config content is available. Use $controller->customError() to render an error response with custom content.
 */
class CustomErrorControllerExtension extends Extension
{
    use Configurable;

    private static $custom_fields = [];

    private static $default_controller = '';

    private static $default_template = '';

    /**
     * Used by {@see RequestHandler::httpError}
     *
     * @param int $statusCode
     * @param HTTPRequest $request
     * @param string $errorMessage
     * @throws HTTPResponse_Exception
     */
    public function onBeforeHTTPError($errorCode, $request, $errorMessage = null)
    {
        // Use provided error message only if no content is specified in yml config. This is because the error messages used in non-user code when calling ->httpError may not be suitable to show to a user.
        $customFieldsConfig = $this->config()->get('custom_fields');
        $customFields = [];
        if (!isset($customFieldsConfig['e' .$errorCode])) $customFields['Content'] = $errorMessage;
        return $this->customError($errorCode, $customFields);
    }

    public function defaultCustomFieldsFor($errorCode)
    {
        // Get content from yml config for a default
        $defaultCustomFields = [];
        $configCustomFields = $this->config()->get('custom_fields');
        $configCustomFields = isset($configCustomFields['e' . $errorCode]) ? $configCustomFields['e' . $errorCode] : $configCustomFields['default'];
        foreach ($configCustomFields as $fieldName => $fieldInfo) {
            // Create a db field if type is specified, otherwise pass plain text
            if (is_array($fieldInfo)) {
                $defaultCustomFields[$fieldName] = DBField::create_field($fieldInfo['Type'], $fieldInfo['Value']);
            } else {
                $defaultCustomFields[$fieldName] = $fieldInfo;
            }
        }
        return $defaultCustomFields;
    }


    public function customError($errorCode, $customFields, $template = null, $controllerType = null)
    {
        // Simple/default response if ajax
        if (Director::is_ajax()) {
            throw new HTTPResponse_Exception($errorMessage, $errorCode);
        }

        // Hey, what's up? If you know lots about controllers, requests and responses, feel free to rewrite all of this.

        // Otherwise build a themed response
        if (!$controllerType) $controllerType = $this->config()->get('default_controller');
        $templates = [];
        if ($template) $templates[] = $template;
        // Fallback to default template
        $templates[] = $this->config()->get('default_template');
        $templates[] = 'Page';
        $defaultCustomFields = $this->defaultCustomFieldsFor($errorCode);
        $response = new HTTPResponse();
        $response->setStatusCode($errorCode);
        // Reset current page in case we're getting a 404 on a nested page. This prevents menus being styled according to parent pages.
        Director::set_current_page(null);
        // Set some default properties for the page, then override with custom ones if provided
        $customFields = array_merge(
            [
                // Use title from config if set, otherwise fall back to framework definition
                'Title' => $response->getStatusDescription(),
                // Add the error code so it's available in templates
                'ErrorResponseCode' => $errorCode,
            ],
            $defaultCustomFields,
            $customFields
        );
        // Create a dummy page to act as a failover for the controller.  Remove 'Controller' from class name to get related Page type
        $pageType = substr_replace($controllerType, '', strrpos($controllerType, 'Controller'), strlen('Controller'));
        $dataRecord = $pageType::create();
        // Negative ID as it's a fake. Use error code so we have an ID for partial caching etc.
        $dataRecord->ID = -$errorCode;
        $dataRecord->update($customFields);
        // Create a request with an empty session, so session data is not rendered and potentially lost to error responses.
        $request = new HTTPRequest('GET', '');
        $request->setSession(new Session([]));
        // Render the response body
        $controller = $controllerType::create($dataRecord);

        // To Do: all of this...
        $controller->setRequest($request);
        $controller->setResponse(new HTTPResponse());
        $controller->doInit();
        $controller->pushCurrent();
        $body = $controller->renderWith($templates, $customFields);
        $controller->popCurrent();
        $response->setBody($body);
        // ... Could be replaced with just this, but would lose custom template support
        // $response = $controller->handleRequest($request);

        if ($response) {
            throw new HTTPResponse_Exception($response, $errorCode);
        }
    }
}
