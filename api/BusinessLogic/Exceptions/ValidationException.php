<?php

namespace BusinessLogic\Exceptions;

use BusinessLogic\ValidationModel;
use Exception;

class ValidationException extends ApiFriendlyException {
    /**
     * ValidationException constructor.
     * @param ValidationModel $validationModel The validation model
     * @throws Exception If the validationModel's errorKeys is empty
     */
    function __construct($validationModel) {
        if (count($validationModel->errorKeys) === 0) {
            throw new Exception('Tried to throw a ValidationException, but the validation model was valid or had 0 error keys!');
        }

        parent::__construct(implode(",", $validationModel->errorKeys), "Validation Failed. Error keys are available in the message section.", 400);
    }
}