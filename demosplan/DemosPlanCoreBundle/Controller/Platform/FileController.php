<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Platform;

use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Response\BinaryFileDownload;
use demosplan\DemosPlanCoreBundle\ValueObject\FileInfo;
use Exception;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class FileController extends BaseController
{
    /**
     * Serve file.
     *
     * @DplanPermissions("area_main_file")
     *
     * @return BinaryFileDownload|Response
     */
    #[Route(path: '/file/{hash}', name: 'core_file', options: ['expose' => true])]
    public function fileAction(FileService $fileService, string $hash)
    {
        try {
            return $this->prepareResponseWithHash($fileService, $hash, true);
        } catch (Exception $e) {
            $this->getLogger()->info('Could not serve file: ', [$e]);
            throw new NotFoundHttpException();
        }
    }

    /**
     * Check Procedure permissions when procedureId is given in route and serve file if allowed.
     *
     * @DplanPermissions("area_main_file")
     */
    #[Route(path: '/file/{procedureId}/{hash}', name: 'core_file_procedure', options: ['expose' => true])]
    public function fileProcedureAction(FileService $fileService, string $procedureId, string $hash): Response
    {
        try {
            return $this->prepareResponseWithHash($fileService, $hash, true, $procedureId);
        } catch (Exception $e) {
            $this->getLogger()->info('Could not serve Procedure file: ', [$e]);
            throw new NotFoundHttpException();
        }
    }

    /**
     * Distinct route for ai api file access to allow for jwt authentication via query parameter.
     * Check Procedure permissions when procedureId is given in route and serve file if allowed.
     */
    #[\demosplan\DemosPlanCoreBundle\Attribute\DplanPermissions(permissions: ['area_main_file'])]
    #[Route(path: '/api/ai/file/{procedureId}/{hash}', name: 'core_file_procedure_api_ai', options: ['expose' => true])]
    public function fileProcedureApiAction(FileService $fileService, string $procedureId, string $hash): Response
    {
        try {
            return $this->prepareResponseWithHash($fileService, $hash, true, $procedureId);
        } catch (Exception $e) {
            $this->getLogger()->info('Could not serve Procedure Api ai file: ', [$e]);
            throw new NotFoundHttpException();
        }
    }

    /**
     * @throws Exception
     */
    protected function prepareResponseWithHash(FileService $fileService, string $hash, bool $strictCheck = false, string $procedureId = null): Response
    {
        $fs = new Filesystem();
        // @improve T14122
        $file = $fileService->getFileInfo($hash, $procedureId);

        // ensure that procedure access check matches file procedure
        if (!$this->isValidProcedure($procedureId, $file, $strictCheck)) {
            $this->getLogger()->info(
                'Tried to access file from different Procedure: ',
                [$file->getProcedure()->getId(), $procedureId]
            );
            throw new NotFoundHttpException();
        }

        // when all procedure bound calls to core_file are replaced by core_file_procedure,
        // access to $file that has a procedure could be declined when called without a procedure

        $this->getLogger()->info('trying to serve file '.$file->getAbsolutePath());

        // check whether file exists
        if (!$fs->exists($file->getAbsolutePath())) {
            $this->getLogger()->info('Could not find file');
            throw new NotFoundHttpException();
        }

        $response = new BinaryFileDownload($file->getAbsolutePath(), $file->getFileName());
        // do not delete Files after delivering
        $response->deleteFileAfterSend(false);

        return $response;
    }

    private function isValidProcedure(?string $procedureId, FileInfo $file, bool $strictCheck): bool
    {
        // do not check if no procedure is given
        if (!$strictCheck && null === $procedureId) {
            return true;
        }

        // When file is bound to a procedure check it
        if ($file->getProcedure() instanceof Procedure && $file->getProcedure()->getId() !== $procedureId) {
            return false;
        }

        return true;
    }

    /**
     * Serve uploaded image.
     *
     * TODO: This should probably be renamed to `core_image`, `core_logo` is misleading
     *
     * @DplanPermissions("area_demosplan")
     *
     * @param string $hash
     */
    #[Route(path: '/image/{hash}', name: 'core_logo', options: ['expose' => true])]
    public function imageAction(Request $request, FileService $fileService, $hash): Response
    {
        // Für den Abruf der Bilder muss keine extra Session gestartet werden

        try {
            // create a Response with an ETag and/or a Last-Modified header
            $notModifiedResponse = new Response();
            $notModifiedResponse->setEtag(md5($hash));

            // Set response as public. Otherwise it will be private by default.
            $notModifiedResponse->setPublic();

            // Check that the Response is not modified for the given Request
            if ($notModifiedResponse->isNotModified($request)) {
                // return the 304 Response immediately
                return $notModifiedResponse;
            }

            $fs = new Filesystem();
            $file = $fileService->getFileInfo($hash);

            // check whether file exists
            if (!$fs->exists($file->getAbsolutePath())) {
                throw new NotFoundHttpException();
            }

            $response = new BinaryFileDownload($file->getAbsolutePath(), $file->getFileName());
        } catch (Exception) {
            // gib ein Standardbild zurück
            $response = new BinaryFileDownload($fileService->getNotFoundImagePath(), '');
        }

        $response->setPublic(); // make sure the response is public/cacheable
        $response->setEtag(md5($hash));
        // do not delete Files after delivering
        $response->deleteFileAfterSend(false);

        return $response;
    }
}
