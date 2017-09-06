<?php

namespace BusinessLogic\Emails;


use BusinessLogic\Tickets\Attachment;
use BusinessLogic\Tickets\Ticket;
use PHPMailer;

class BasicEmailSender implements EmailSender {

    function sendEmail($emailBuilder, $heskSettings, $modsForHeskSettings, $sendAsHtml) {
        $mailer = new PHPMailer();

        if ($heskSettings['smtp']) {
            $mailer->isSMTP();
            $mailer->SMTPAuth = true;

            //-- We'll set this explicitly below if the user has it enabled.
            $mailer->SMTPAutoTLS = false;
            
            if ($heskSettings['smtp_ssl']) {
                $mailer->SMTPSecure = "ssl";
            } elseif ($heskSettings['smtp_tls']) {
                $mailer->SMTPSecure = "tls";
            }
            $mailer->Host = $heskSettings['smtp_host_name'];
            $mailer->Port = $heskSettings['smtp_host_port'];
            $mailer->Username = $heskSettings['smtp_user'];
            $mailer->Password = $heskSettings['smtp_password'];
        }

        $mailer->FromName = $heskSettings['noreply_name'] !== null &&
                            $heskSettings['noreply_name'] !== '' ? $heskSettings['noreply_name'] : '';
        $mailer->From = $heskSettings['noreply_mail'];

        if ($emailBuilder->to !== null) {
            foreach ($emailBuilder->to as $to) {
                $mailer->addAddress($to);
            }
        }

        if ($emailBuilder->cc !== null) {
            foreach ($emailBuilder->cc as $cc) {
                $mailer->addCC($cc);
            }
        }

        if ($emailBuilder->bcc !== null) {
            foreach ($emailBuilder->bcc as $bcc) {
                $mailer->addBCC($bcc);
            }
        }

        $mailer->Subject = $emailBuilder->subject;

        if ($sendAsHtml) {
            $mailer->Body = $emailBuilder->htmlMessage;
            $mailer->AltBody = $emailBuilder->message;
        } else {
            $mailer->Body = $emailBuilder->message;
            $mailer->isHTML(false);
        }
        $mailer->Timeout = $heskSettings['smtp_timeout'];

        if ($emailBuilder->attachments !== null) {
            foreach ($emailBuilder->attachments as $attachment) {
                $mailer->addAttachment(__DIR__ . '/../../../' . $heskSettings['attach_dir'] . '/' . $attachment->savedName,
                    $attachment->fileName);
            }
        }

        if ($mailer->send()) {
            return true;
        }

        return $mailer->ErrorInfo;
    }
}