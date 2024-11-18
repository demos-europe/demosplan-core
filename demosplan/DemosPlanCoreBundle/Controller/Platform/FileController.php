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
use League\Flysystem\FilesystemOperator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class FileController extends BaseController
{
    public function __construct(
        private readonly FilesystemOperator $defaultStorage,
        private readonly FilesystemOperator $localStorage,
    ) {
    }

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
    protected function prepareResponseWithHash(FileService $fileService, string $hash, bool $strictCheck = false, ?string $procedureId = null): Response
    {
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

        $this->getLogger()->info('trying to serve file ', [$file->getAbsolutePath()]);

        return $this->getStreamedResponse($file);
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

            $file = $fileService->getFileInfo($hash);
            $response = $this->getStreamedResponse($file);
        } catch (Exception) {
            // return default image
            $response = new BinaryFileDownload($fileService->getNotFoundImagePath(), '');
        }

        $response->setPublic(); // make sure the response is public/cacheable
        $response->setEtag(md5($hash));

        return $response;
    }

    private function getStreamedResponse(FileInfo $file): StreamedResponse
    {
        // check whether file exists using Default storage
        if ($this->defaultStorage->fileExists($file->getAbsolutePath())) {
            $storage = $this->defaultStorage;
        }
        // as a fallback check whether file exists using Local storage
        elseif ($this->localStorage->fileExists($file->getAbsolutePath())) {
            $storage = $this->localStorage;
        } else {
            $this->getLogger()->info('Could not find file', [$file->getAbsolutePath()]);
            throw new NotFoundHttpException();
        }

        // create a stream from the file content
        $stream = $storage->readStream($file->getAbsolutePath());

        // create a response with the stream content
        $response = new StreamedResponse(function () use ($stream) {
            fpassthru($stream);
            fclose($stream);
        });
        $response->headers->set('Content-Type', 'application/octet-stream');
        $response->headers->set(
            'Content-Disposition',
            'attachment; filename="'.$file->getFileName().'"'
        );

        return $response;
    }
}
