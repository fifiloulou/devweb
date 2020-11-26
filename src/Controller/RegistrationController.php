<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\SendEmail;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Finder\Exception\AccessDeniedException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;

class RegistrationController extends AbstractController
{
    /**
     * @Route("/register", name="app_register")
     */
    public function register(
        EntityManagerInterface $entityManager,
        Request $request,
        SendEmail $sendEmail,
        TokenGeneratorInterface $tokenGenerator,
        UserPasswordEncoderInterface $passwordEncoder
    ): Response
    {
        $user = new User();

        $form = $this->createForm(RegistrationFormType::class, $user);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $registrationToken = $tokenGenerator->generateToken();
            $user->setRegistrationToken($registrationToken)
                 ->setPassword($passwordEncoder->encodePassword($user, $form->get('password')->getData()));

            $entityManager->persist($user);

            $entityManager->flush();

            $sendEmail->send([
                'recipient_email' => $user->getEmail(),
                'subject'         => "Vérification de votre adresse mail pour activer votre compte utilisateur",
                'html_template'   =>  "registration/register_confirmation_email.html.twig",
                'context'         => [
                    'userID'            => $user->getId(),
                    'registrationToken' => $registrationToken,
                    'tokenLifeTime'     => $user->getAccountMustBeVerifiedBefore()->format('d/m/Y à H:i')
                ]
            ]);

            $this->addFlash('success', "Votre compte utilisateur a bien été créé veuillez consulter vos mails pour l'activer");

            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id<\d+>}/{token}", name="app_verify_account", methods={"GET"})
     */
    public function verifyUserAccount(EntityManagerInterface $entityManager, User $user, string $token) : Response
    {
        if (($user->getRegistrationToken() === null) || ($user->getRegistrationToken() !== $token) || ($this->isNotRequestedInTime($user->getAccountMustBeVerifiedBefore()))) {
            throw new AccessDeniedException();
        }

        $user->setIsVerified(true);

        $user->setAccountVerifiedAt(new \DateTimeImmutable('now'));

        $user->setRegistrationToken(null);

        $entityManager->flush();

        $this->addFlash('success', 'Votre compte utilisateur est dès à present activé, vous pouvez vous connecter !');

        return $this->redirectToRoute('app_login');
    }

    private function isNotRequestedInTime(\DateTimeImmutable $accountMustBeVerifiedBefore): bool
    {
        return (new \DateTimeImmutable('now') > $accountMustBeVerifiedBefore);
    }
}