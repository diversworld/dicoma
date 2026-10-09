<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[Route('/files')]
class FileController extends AbstractController
{
    private string $dirPath; // path to the directory with files

    public function __construct(ParameterBagInterface $params)
    {
        $this->dirPath = $params->get('bilder_ordner');
    }

    #[Route('/', name: 'app_files_index')]
    public function index(): Response
    {
        return $this->render('file/index.html.twig', [
            'controller_name' => 'FileController',
        ]);
    }

    #[Route('/list', name: 'app_files_list')]
    public function listFiles(): Response
    {
        $files = scandir($this->dirPath);
        $files = array_diff($files, ['.', '..']); // remove . and ..

        if ($files === false) {
            throw new NotFoundHttpException('Directory not found or is not readable.');
        }

        return $this->render('files/index.html.twig', ['files' => $files]);
    }

    #[Route('/choose', name: 'app_files_choose', methods: ['POST'] )]
    public function chooseFile(Request $request): Response
    {
        $filename = $request->request->get('filename');
        $filePath = $this->dirPath . '/' . $filename;

        if (!file_exists($filePath)) {
            throw new NotFoundHttpException('File does not exist.');
        }

        // Process the file here...
        //return $request;
    }

    #[Route('/upload', name: 'app_file_upload', methods: ['POST'] )]
    public function uploadFile(Request $request): Response
    {
        $form = $this->createFormBuilder()
            ->add('file', FileType::class)
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form['file']->getData();
            $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $newFilename = $originalFilename.'-'.uniqid().'.'.$file->guessExtension();

            try {
                $file->move(
                    $this->dirPath,
                    $newFilename
                );
            } catch (FileException $e) {
                // ... handle exception if something happens during file upload
            }

            // redirect to some other page or path after the file is uploaded
            return $this->redirectToRoute('file_list');
        }

        return $this->render('files/upload.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
