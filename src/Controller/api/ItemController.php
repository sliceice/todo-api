<?php

namespace App\Controller\api;

use App\Services\UploadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Item;
use App\Entity\ItemList;
use App\Entity\User;
use App\Exception\JsonHttpException;
use App\Normalizer\ItemNormalizer;
use App\Security\ApiAuthenticator;
use App\Services\ValidateService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ItemController extends AbstractController
{
    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var ValidateService
     */
    private $validateService;

    public function __construct(SerializerInterface $serializer, ValidateService $validateService)
    {
        $this->serializer = $serializer;
        $this->validateService = $validateService;
    }

    /**
     * @Route("/api/lists/{id}", methods={"POST"}, name="api_item_add")
     */
    public function addAction(Request $request, ItemList $itemList)
    {
        $user = $this->getDoctrine()->getRepository(User::class)->findOneByApiToken($request->headers->get(ApiAuthenticator::X_API_KEY));
        if (!($itemList->getUser() === $user))
            throw new JsonHttpException(400, "Bad request");

        /* @var Item $item */
        $item = $this->serializer->deserialize($request->getContent(), Item::class, 'json');
        $this->validateService->validate($item);

        $itemList->addItem($item);
        $this->getDoctrine()->getManager()->flush();

        return $this->json($item, 200, [], [AbstractNormalizer::GROUPS => [ItemNormalizer::GROUP_DETAILS]]);
    }

    /**
     * @Route("/api/lists/{id}/item/{item}", methods={"GET"}, name="api_item_show")
     */
    public function showAction(Request $request, ItemList $itemList, Item $item)
    {
        $user = $this->getDoctrine()->getRepository(User::class)->findOneByApiToken($request->headers->get(ApiAuthenticator::X_API_KEY));
        if (!($itemList->getUser() === $user) || !($item->getItemList() === $itemList))
            throw new JsonHttpException(400, "Bad request");

        return $this->json($item, 200, [], [AbstractNormalizer::GROUPS => [ItemNormalizer::GROUP_DETAILS]]);
    }

    /**
     * @Route("/api/lists/{id}/item/{item}", methods={"DELETE"}, name="api_item_delete")
     */
    public function deleteAction(Request $request, ItemList $itemList, Item $item)
    {
        $user = $this->getDoctrine()->getRepository(User::class)->findOneByApiToken($request->headers->get(ApiAuthenticator::X_API_KEY));
        if (!($itemList->getUser() === $user) || !($item->getItemList() === $itemList))
            throw new JsonHttpException(400, "Bad request");

        $this->getDoctrine()->getManager()->remove($item);
        $this->getDoctrine()->getManager()->flush();

        return $this->json('ok');
    }

    /**
     * @Route("/api/lists/{id}/item/{item}", methods={"PUT"}, name="api_item_edit")
     */
    public function editAction(Request $request, ItemList $itemList, Item $item)
    {
        $user = $this->getDoctrine()->getRepository(User::class)->findOneByApiToken($request->headers->get(ApiAuthenticator::X_API_KEY));
        if (!($itemList->getUser() === $user) || !($item->getItemList() === $itemList))
            throw new JsonHttpException(400, "Bad request");

        if ($request->query->has('isChecked')) {
            $item->setIsChecked($request->query->get('isChecked'));
            $this->getDoctrine()->getManager()->flush();
        }

        return $this->json('ok');
    }

    /**
     * @Route("/api/lists/{id}/item/{item}/attachment", methods={"POST"}, name="api_item_attachment_add")
     */
    public function setAttachmentAction(Request $request, ItemList $itemList, Item $item, UploadService $uploadService)
    {
        $user = $this->getDoctrine()->getRepository(User::class)->findOneByApiToken($request->headers->get(ApiAuthenticator::X_API_KEY));
        if (!($itemList->getUser() === $user) || !($item->getItemList() === $itemList))
            throw new JsonHttpException(400, "Bad request");

        if ($request->files->count()) {
            $attachment = $uploadService->uploadAttachment($request->files->get("attachment"));
            $item->setAttachment($attachment);
        } else {
            $item->setAttachment(null);
        }

        $this->getDoctrine()->getManager()->flush();

        return $this->json("ok");
    }
}
