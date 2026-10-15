<?php

declare(strict_types=1);

namespace Playground\Controller;

use Playground\Form\DemoType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DemoController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function home(): Response
    {
        return $this->render('home.html.twig');
    }

    #[Route('/assetmapper', name: 'assetmapper')]
    public function assetMapper(Request $request): Response
    {
        return $this->demo($request, 'base_assetmapper.html.twig');
    }

    #[Route('/encore', name: 'encore')]
    public function encore(Request $request): Response
    {
        return $this->demo($request, 'base_encore.html.twig');
    }

    /**
     * Stands in for a file manager: tells the opener which file was picked, through the default adapter protocol.
     */
    #[Route('/file-manager', name: 'file_manager')]
    public function fileManager(): Response
    {
        return new Response(<<<'HTML'
            <button id="pick" type="button">Pick /pick.png</button>
            <script>
              document.getElementById('pick').addEventListener('click', () => {
                window.opener.postMessage(
                  { type: 'umanit-tiptap:file-selected', url: '/pick.png' },
                  window.location.origin,
                );
                window.close();
              });
            </script>
            HTML);
    }

    private function demo(Request $request, string $layout): Response
    {
        $form = $this->createForm(DemoType::class, ['body' => '<p>Hello <strong>TipTap</strong></p>']);
        $form->handleRequest($request);

        return $this->render('demo.html.twig', [
            'layout'    => $layout,
            'form'      => $form,
            'submitted' => $form->isSubmitted() ? $form->get('body')->getData() : null,
        ]);
    }
}
