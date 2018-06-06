<?php

namespace JonoM\CustomErrors;

use Page;
use PageController;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\HTTPResponse_Exception;
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
        $customFields = [];
        if (!isset($this->config()->get('custom_fields')['e' .$errorCode])) $customFields['Content'] = $errorMessage;
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
        // Otherwise build a themed response
        if (!$controllerType) $controllerType = $this->config()->get('default_controller');
        if (!$template) $template = $this->config()->get('default_template');
        $defaultCustomFields = $this->defaultCustomFieldsFor($errorCode);
        $response = new HTTPResponse();
        $response->setStatusCode($errorCode);
        // Set some default properties, then override with custom ones if provided
        $customFields = array_merge(
            // Use title from config if set, otherwise fall back to framework definition
            ['Title' => $response->getStatusDescription()],
            $defaultCustomFields,
            $customFields
        );
        $body = $controllerType::create()->renderWith($template, $customFields);
        $response->setBody($body);
        if ($response) {
            throw new HTTPResponse_Exception($response, $errorCode);
        }
    }
}
