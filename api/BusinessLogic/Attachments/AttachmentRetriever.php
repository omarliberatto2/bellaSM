<?php

namespace BusinessLogic\Attachments;


use BusinessLogic\Exceptions\AccessViolationException;
use BusinessLogic\Exceptions\ApiFriendlyException;
use BusinessLogic\Security\UserToTicketChecker;
use DataAccess\Attachments\AttachmentGateway;
use DataAccess\Files\FileReader;
use DataAccess\Tickets\TicketGateway;

class AttachmentRetriever {
    /* @var $attachmentGateway AttachmentGateway */
    private $attachmentGateway;

    /* @var $fileReader FileReader */
    private $fileReader;

    /* @var $ticketGateway TicketGateway */
    private $ticketGateway;

    /* @var $userToTicketChecker UserToTicketChecker */
    private $userToTicketChecker;

    function __construct($attachmentGateway, $fileReader, $ticketGateway, $userToTicketChecker) {
        $this->attachmentGateway = $attachmentGateway;
        $this->fileReader = $fileReader;
        $this->ticketGateway = $ticketGateway;
        $this->userToTicketChecker = $userToTicketChecker;
    }

    //-- TODO Test
    function getAttachmentContentsForTrackingId($trackingId, $attachmentId, $userContext, $heskSettings) {
        $ticket = $this->ticketGateway->getTicketByTrackingId($trackingId, $heskSettings);

        if ($ticket === null) {
            throw new ApiFriendlyException("Ticket {$trackingId} not found!", "Ticket Not Found", 404);
        }

        $attachment = $this->attachmentGateway->getAttachmentById($attachmentId, $heskSettings);

        return array('meta' => $attachment,
            'contents' => $this->fileReader->readFromFile($attachment->savedName, $heskSettings['attach_dir']));
    }

    function getAttachmentContentsForTicket($ticketId, $attachmentId, $userContext, $heskSettings) {
        $ticket = $this->ticketGateway->getTicketById($ticketId, $heskSettings);

        if ($ticket === null) {
            throw new ApiFriendlyException("Ticket {$ticketId} not found!", "Ticket Not Found", 404);
        }

        if (!$this->userToTicketChecker->isTicketAccessibleToUser($userContext, $ticket, $heskSettings)) {
            throw new AccessViolationException("User does not have access to attachment {$attachmentId}!");
        }

        $attachment = $this->attachmentGateway->getAttachmentById($attachmentId, $heskSettings);
        $contents = base64_encode($this->fileReader->readFromFile(
            $attachment->savedName, $heskSettings['attach_dir']));

        return $contents;
    }
}