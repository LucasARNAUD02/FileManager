<?php

namespace Lucas\FileManager\Controller;

use App\Constants\PermissionsIds;
use App\Entity\Cloud\DocumentRecent;
use App\Entity\Cloud\HistoriqueCloud;
use App\Repository\Cloud\DocumentRecentRepository;
use App\Service\PermissionChecker;
use Doctrine\ORM\EntityManagerInterface;
use Lucas\FileManager\Event\FileManagerEvents;
use Lucas\FileManager\Helpers\File;
use Lucas\FileManager\Helpers\FileManager;
use Lucas\FileManager\Helpers\FileManagerUploadHandler;
use Lucas\FileManager\Service\FilemanagerService;
use Lucas\FileManager\Service\FileTypeService;
use Lucas\FileManager\Twig\OrderExtension;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

/**
 * @author Arthur Gribet <a.gribet@gmail.com>
 */
class ManagerController extends AbstractController
{

    private FileManager $fileManager;

    /**
     * ManagerController constructor.
     */
    public function __construct(
        private FilemanagerService                $fileManagerService,
        private EventDispatcherInterface          $dispatcher,
        private TranslatorInterface               $translator,
        private RouterInterface                   $router,
        private FormFactoryInterface              $formFactory,
        private EntityManagerInterface            $em,
        private PermissionChecker                 $permissionChecker,
        private readonly DocumentRecentRepository $documentRecentRepository
    )
    {
    }

    /**
     * @Route("/", name="file_manager", options={"expose"=true})
     */
    public function indexAction(Request $request, FileTypeService $fileTypeService): JsonResponse|Response
    {
        $queryParameters = $request->query->all();
        $isJson = $request->get('json');
        if ($isJson) {
            unset($queryParameters['json']);
        }

        $fileManager = $this->newFileManager($queryParameters);

        // Folder search
        $directoriesArbo = $this->retrieveSubDirectories($fileManager, $fileManager->getDirName(), \DIRECTORY_SEPARATOR, $fileManager->getBaseName());

        // File search
        $finderFiles = new Finder();
        $finderFiles->in($fileManager->getCurrentPath())->depth(0);
        $regex = $fileManager->getRegex();

        $orderBy = $fileManager->getQueryParameter('orderby');
        $orderDESC = OrderExtension::DESC === $fileManager->getQueryParameter('order');
        if (!$orderBy) {
            $finderFiles->sortByType();
        }

        switch ($orderBy) {
            case 'name':
                $finderFiles->sort(function (SplFileInfo $a, SplFileInfo $b) {
                    return strcmp(mb_strtolower($b->getFilename()), mb_strtolower($a->getFilename()));
                });
                break;
            case 'date':
                $finderFiles->sortByModifiedTime();
                break;
            case 'size':
                $finderFiles->sort(function (\SplFileInfo $a, \SplFileInfo $b) {
                    return $a->getSize() - $b->getSize();
                });
                break;
        }

        $finderFiles->filter(function (SplFileInfo $file) use ($regex) {
            if ('file' === $file->getType()) {
                if (preg_match($regex, $file->getFilename())) {
                    return $file->isReadable();
                }

                return false;
            }

            return $file->isReadable();
        });

        $this->dispatch(FileManagerEvents::POST_FILE_FILTER_CONFIGURATION, ['finder' => $finderFiles]);

        $formDelete = $this->createDeleteForm()->createView();
        $fileArray = [];

        foreach ($finderFiles as $file) {
            $fileArray[] = new File($file, $this->translator, $fileTypeService, $fileManager);
        }

        if ('dimension' === $orderBy) {
            usort($fileArray, static function (File $a, File $b) {
                $aDimension = $a->getDimension();
                $bDimension = $b->getDimension();
                if ($aDimension && !$bDimension) {
                    return 1;
                }

                if (!$aDimension && $bDimension) {
                    return -1;
                }

                if (!$aDimension && !$bDimension) {
                    return 0;
                }

                return ($aDimension[0] * $aDimension[1]) - ($bDimension[0] * $bDimension[1]);
            });
        }

        if ($orderDESC) {
            $fileArray = array_reverse($fileArray);
        }

        $parameters = [
            'fileManager' => $fileManager,
            'fileArray' => $fileArray,
            'formDelete' => $formDelete,
        ];
        if ($isJson) {
            $fileList = $this->renderView('@FileManager/views/_manager_view.html.twig', $parameters);

            return new JsonResponse(['data' => $fileList, 'badge' => $finderFiles->count(), 'treeData' => $directoriesArbo]);
        }
        $parameters['treeData'] = json_encode($directoriesArbo, JSON_THROW_ON_ERROR);

        $form = $this->formFactory->createNamedBuilder('rename')
            ->add('name', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                ],
                'label' => false,
                'data' => $this->translator->trans('input.default'),
            ])
            ->add('send', SubmitType::class, [
                'attr' => [
                    'class' => 'btn btn-sm btn-success',
                ],
                'label' => $this->translator->trans('button.save'),
            ])
            ->getForm();

        /* @var Form $form */
        $form->handleRequest($request);
        /** @var Form $formRename */
        $formRename = $this->createRenameForm();

        if ($form->isSubmitted()) {

            if($form->isValid()){

                $this->permissionChecker->checkPermissionUser(PermissionsIds::GERER_CLOUD_COMMUN, true);

                $data = $form->getData();
                $fs = new Filesystem();
                $directory = $directorytmp = $fileManager->getCurrentPath() . \DIRECTORY_SEPARATOR . $data['name'];
                $i = 1;

                while ($fs->exists($directorytmp)) {
                    $directorytmp = "{$directory} ({$i})";
                    ++$i;
                }
                $directory = $directorytmp;

                try {

                    $fs->mkdir($directory);

                    $historiqueCloud = new HistoriqueCloud();

                    $historiqueCloud
                        ->setAction("Ajout")
                        ->setDate(new \DateTime())
                        ->setUser($this->getUser())
                        ->setPath(explode("\bibliotheque", $directory)[1]);

                    $this->em->persist($historiqueCloud);
                    $this->em->flush();

                    $this->addFlash('success', $this->translator->trans('folder.add.success'));
                } catch (IOExceptionInterface $e) {
                    $this->addFlash('error', $this->translator->trans('folder.add.danger', ['%message%' => $data['name']]));
                }

            } else {

                $errors = $form->getErrors(true);

                foreach ($errors as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
            }

            return $this->redirectToRoute('file_manager', $fileManager->getQueryParameters());
        }

        // fin submit

        $parameters['form'] = $form->createView();
        $parameters['formRename'] = $formRename->createView();

        return $this->render('@FileManager/manager.html.twig', $parameters);
    }

    private function newFileManager(array $queryParameters): FileManager
    {

        if (!isset($queryParameters['conf'])) {
            throw new \RuntimeException('Please define a conf parameter in your route');
        }

        $webDir = $this->getParameter('file_manager')['web_dir'];
        $this->fileManager = new FileManager($queryParameters, $this->fileManagerService->getBasePath($queryParameters), $this->router, $this->dispatcher, $webDir);

        return $this->fileManager;
    }

    private function retrieveSubDirectories(FileManager $fileManager, string $path, ?string $parent = \DIRECTORY_SEPARATOR, ?string $baseFolderName = null): ?array
    {
        $directories = new Finder();
        $directories->in($path)->ignoreUnreadableDirs()->directories()->depth(0)->sortByType()->filter(function (SplFileInfo $file) {
            return $file->isReadable();
        });

        $this->dispatch(FileManagerEvents::POST_DIRECTORY_FILTER_CONFIGURATION, ['finder' => $directories]);

        if ($baseFolderName) {
            $directories->name($baseFolderName);
        }
        $directoriesList = null;

        foreach ($directories as $directory) {
            /** @var SplFileInfo $directory */
            $directoryFileName = $directory->getFilename();
            $fileName = $baseFolderName ? '' : $parent . $directoryFileName;

            $queryParameters = $fileManager->getQueryParameters();
            $queryParameters['route'] = $fileName;
            $queryParametersRoute = $queryParameters;
            unset($queryParametersRoute['route']);

            $fileSpan = '';

            if (true === $fileManager->getConfiguration()['show_file_count']) {
                $filesNumber = $this->retrieveFilesNumber($directory->getPathname(), $fileManager->getRegex());
                $directoriesNumber = $this->retrieveDirectoriesNumber($directory->getPathname());
                $total = $filesNumber + $directoriesNumber;
                $fileSpan = $total > 0 ? " <span class='badge'>$total</span>" : '';
            }

            if ($fileName === '' && isset($fileManager->getConfiguration()['root_name'])) {
                $directoryFileName = $fileManager->getConfiguration()['root_name'];
            }

            $opened = str_replace(['/', '\\'], '', $fileName) === str_replace(['/', '\\'], '', $fileManager->getCurrentRoute());

            $directoriesList[] = [
                'text' => $directoryFileName . $fileSpan,
                'icon' => 'far fa-folder-open',
                'children' => $this->retrieveSubDirectories($fileManager, $directory->getPathname(), $fileName . \DIRECTORY_SEPARATOR),
                'a_attr' => [
                    'href' => $fileName ? $this->generateUrl('file_manager', $queryParameters) : $this->generateUrl('file_manager', $queryParametersRoute),
                ],
                'state' => [
                    'opened' => $opened,
                    'selected' => $opened,
                ],
            ];

        }

        return $directoriesList;
    }

    protected function dispatch(string $eventName, array $arguments = [])
    {
        $arguments = array_replace([
            'filemanager' => $this->fileManager,
        ], $arguments);

        $subject = $arguments['filemanager'];
        $event = new GenericEvent($subject, $arguments);
        $this->dispatcher->dispatch($event, $eventName);
    }

    /**
     * Tree Iterator.
     */
    private function retrieveFilesNumber(string $path, string $regex): int
    {
        $files = new Finder();

        $files->in($path)->files()->depth(0)->name($regex);

        $this->dispatch(FileManagerEvents::POST_FILE_FILTER_CONFIGURATION, ['finder' => $files]);

        return iterator_count($files);
    }

    private function retrieveDirectoriesNumber(string $path): int
    {
        $directories = new Finder();

        $directories->in($path)->directories()->depth(0);

        $this->dispatch(FileManagerEvents::POST_FILE_FILTER_CONFIGURATION, ['finder' => $directories]);

        return iterator_count($directories);
    }

    private function createDeleteForm(): FormInterface|Form
    {

        return $this->formFactory->createNamedBuilder('delete_f')
            ->add('DELETE', SubmitType::class, [
                'translation_domain' => 'messages',
                'attr' => [
                    'class' => 'btn btn-sm btn-danger',
                ],
                'label' => 'button.delete.action',
            ])
            ->getForm();
    }

    private function createRenameForm(): FormInterface|Form
    {
        return $this->formFactory->createNamedBuilder('rename_f')
            ->add('name', TextType::class, [
                'attr' => [
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank(),
                ],
                'label' => false,
            ])
            ->add('extension', HiddenType::class)
            ->add('send', SubmitType::class, [
                'attr' => [
                    'class' => 'btn btn-sm btn-success',
                ],
                'label' => 'button.rename.action',
            ])
            ->getForm();
    }

    /**
     * @Route("/rename/{fileName}", name="file_manager_rename")
     */
    public function renameFileAction(Request $request, string $fileName): RedirectResponse
    {
        $this->permissionChecker->checkPermissionUser(PermissionsIds::GERER_CLOUD_COMMUN, true);

        $queryParameters = $request->query->all();

        $formRename = $this->createRenameForm();

        /* @var Form $formRename */
        $formRename->handleRequest($request);

        if ($formRename->isSubmitted()) {

            if($formRename->isValid()){

                $data = $formRename->getData();
                $extension = $data['extension'] ? '.' . $data['extension'] : '';
                $newFileName = $data['name'] . $extension;

                if ($newFileName !== $fileName && isset($data['name'])) {

                    $fileManager = $this->newFileManager($queryParameters);
                    $newFilePath = $fileManager->getCurrentPath() . \DIRECTORY_SEPARATOR . $newFileName;
                    $oldFilePath = realpath($fileManager->getCurrentPath() . \DIRECTORY_SEPARATOR . $fileName);

                    // on renomme le document récent si on change le nom du document original

                    $path = $queryParameters["route"] ?? "";

                    $documentRecent = $this->documentRecentRepository->findOneBy(array('fileName' => $fileName, 'path' => $path));

                    if ($documentRecent !== null) {
                        $documentRecent->setFileName($newFileName);
                        $this->em->flush();
                    }

                    if (0 !== mb_strpos($newFilePath, $fileManager->getCurrentPath())) {
                        $this->addFlash('danger', $this->translator->trans('file.renamed.unauthorized'));
                    } else {

                        $fs = new Filesystem();

                        try {
                            $fs->rename($oldFilePath, $newFilePath);
                            $this->addFlash('success', $this->translator->trans('file.renamed.success'));
                            //File has been renamed successfully
                        } catch (IOException $exception) {
                            $this->addFlash('error', $this->translator->trans('file.renamed.danger'));
                        }
                    }
                } else {
                    $this->addFlash('info', $this->translator->trans('file.renamed.nochanged'));
                }
            } else {

                $errors = $formRename->getErrors(true);

                foreach ($errors as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
            }
        }

        return $this->redirectToRoute('file_manager', $queryParameters);
    }

    /**
     * @Route("/upload/", name="file_manager_upload")
     */
    public function uploadFileAction(Request $request): JsonResponse|Response
    {
        $this->permissionChecker->checkPermissionUser(PermissionsIds::GERER_CLOUD_COMMUN, true);

        $fileManager = $this->newFileManager($request->query->all());

        $options = [
            'upload_dir' => $fileManager->getCurrentPath() . \DIRECTORY_SEPARATOR,
            'upload_url' => implode('/', array_map('rawurlencode', explode('/', $fileManager->getImagePath()))),
            'accept_file_types' => $fileManager->getRegex(),
            'print_response' => false,
            'override' => false,
            'image_versions' => array(
                '' => array(
                    'auto_orient' => true
                ),
            ),
        ];
        if (isset($fileManager->getConfiguration()['upload'])) {
            $options = $fileManager->getConfiguration()['upload'] + $options;
        }

        $this->dispatch(FileManagerEvents::PRE_UPDATE, ['options' => &$options]);
        $uploadHandler = new FileManagerUploadHandler($options);
        $response = $uploadHandler->get_response();

        foreach ($response['files'] as $file) {

            if (isset($file->error)) {

                $file->error = $this->translator->trans($file->error);

            } else if (!$fileManager->getImagePath()) {

                $file->url = $this->generateUrl('file_manager_file', array_merge($fileManager->getQueryParameters(), ['fileName' => $file->url]));

                $documentRecent = (new DocumentRecent())
                    ->setDate(new \DateTime())
                    ->setUser($this->getUser())
                    ->setPath(urldecode($fileManager->getQueryParameter('route')))
                    ->setFileName($file->name)
                    ->setExt(pathinfo($file->name, PATHINFO_EXTENSION));

                $this->em->persist($documentRecent);

                $historiqueCloud = new HistoriqueCloud();

                $historiqueCloud
                    ->setDate(new \DateTime())
                    ->setUser($this->getUser())
                    ->setPath(urldecode($fileManager->getQueryParameter('route')) . "/" . $file->name)
                    ->setAction("Ajout");

                $this->em->persist($historiqueCloud);
            }
        }

        $this->em->flush();

        $this->dispatch(FileManagerEvents::POST_UPDATE, ['response' => &$response]);

        return new JsonResponse($response);
    }

    /**
     * @Route("/file/{fileName}", name="file_manager_file")
     */
    public function binaryFileResponseAction(Request $request, string $fileName): BinaryFileResponse|RedirectResponse
    {
        $fileManager = $this->newFileManager($request->query->all());
        $filePath = $file = $fileManager->getCurrentPath() . \DIRECTORY_SEPARATOR;

        // url decode nécéssaire si le fichier est affiché dans une autre page (par exemple depuis les documents récents) car nom récup depuis l'url
        // pas nécéssaire si appellé en js depuis l'iframe du cloud directement car pas dans une url
        if(is_readable($filePath . $fileName)){
            $file = $filePath . $fileName;
        } else {
            $file = $filePath . urldecode($fileName);
        }

        if (!is_readable($file)) {
            $this->addFlash("error", "Le fichier n'existe pas, il a été renommé ou supprimé.");
            return $this->redirectToRoute('file_manager', $fileManager->getQueryParameters());
        }

        $this->dispatch(FileManagerEvents::FILE_ACCESS, ['path' => $file]);

        return new BinaryFileResponse($file);
    }

    /**
     * @Route("/delete/", name="file_manager_delete")
     */
    public function deleteAction(Request $request): RedirectResponse
    {
        $this->permissionChecker->checkPermissionUser(PermissionsIds::GERER_CLOUD_COMMUN, true);

        $form = $this->createDeleteForm();
        $form->handleRequest($request);
        $queryParameters = $request->query->all();

        if ($form->isSubmitted() && $form->isValid()) {
            // remove file
            $fileManager = $this->newFileManager($queryParameters);

            $fs = new Filesystem();
            if (isset($queryParameters['delete'])) {
                $is_delete = false;
                foreach ($queryParameters['delete'] as $fileName) {

                    $filePath = realpath($fileManager->getCurrentPath() . \DIRECTORY_SEPARATOR . $fileName);

                    if (!str_starts_with($filePath, $fileManager->getCurrentPath())) {
                        $this->addFlash('error', $this->translator->trans('file.deleted.danger'));
                    } else {
                        $this->dispatch(FileManagerEvents::PRE_DELETE_FILE);
                        try {

                            $fs->remove($filePath);
                            $is_delete = true;

                            $historiqueCloud = new HistoriqueCloud();

                            $historiqueCloud
                                ->setDate(new \DateTime())
                                ->setUser($this->getUser())
                                ->setAction('Suppression')
                                ->setPath(explode("\bibliotheque", $filePath)[1]);

                            $this->em->persist($historiqueCloud);

                        } catch (IOException $exception) {
                            $this->addFlash('error', $this->translator->trans('file.deleted.unauthorized'));
                        }
                        $this->dispatch(FileManagerEvents::POST_DELETE_FILE);
                    }
                }

                $this->em->flush();

                if ($is_delete) {
                    $this->addFlash('success', $this->translator->trans('file.deleted.success'));
                }

                unset($queryParameters['delete']);

            } else {
                $this->dispatch(FileManagerEvents::PRE_DELETE_FOLDER);
                try {

                    $path = $fileManager->getCurrentPath();

                    $fs->remove($fileManager->getCurrentPath());

                    // suppression du dossier courant

                    $historiqueCloud = (new HistoriqueCloud())
                        ->setDate(new \DateTime())
                        ->setUser($this->getUser())
                        ->setAction('Suppression')
                        ->setPath(explode("\bibliotheque", $path)[1]);

                    $this->em->persist($historiqueCloud);

                    $this->em->flush();

                    $this->addFlash('success', $this->translator->trans('folder.deleted.success'));
                } catch (IOException $exception) {
                    $this->addFlash('error', $this->translator->trans('folder.deleted.unauthorized'));
                }

                $this->dispatch(FileManagerEvents::POST_DELETE_FOLDER);
                $queryParameters['route'] = \dirname($fileManager->getCurrentRoute());
                if ($queryParameters['route'] == "\\" || $queryParameters['route'] === "/") {
                    unset($queryParameters['route']);
                }

                return $this->redirectToRoute('file_manager', $queryParameters);
            }
        }

        return $this->redirectToRoute('file_manager', $queryParameters);
    }
}