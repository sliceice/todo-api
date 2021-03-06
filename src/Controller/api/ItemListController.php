<?php

namespace App\Controller\api;

use App\Entity\ItemList;
use App\Exception\JsonHttpException;
use App\Normalizer\ItemListNormalizer;
use App\Services\LabelService;
use App\Services\ValidateService;
use App\Voter\ItemListVoter;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

class ItemListController extends AbstractController
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
     * @Route("/api/lists", methods={"POST"}, name="api_list_add")
     */
    public function addAction(Request $request, LabelService $labelService)
    {
        /* @var ItemList $itemList */
        $itemList = $this->serializer->deserialize($request->getContent(), ItemList::class, 'json');
        $this->denyAccessUnlessGranted(ItemListVoter::CREATE, $itemList);
        $this->validateService->validate($itemList);
        $labelService->initLabels($itemList->getLabels(), $itemList);
        $this->validateService->validate($itemList->getLabels());
        $itemList->setUser($this->getUser());

        $this->getDoctrine()->getManager()->persist($itemList);
        $this->getDoctrine()->getManager()->flush();

        return $this->json($itemList);
    }

    /**
     * @Route("/api/lists", methods={"GET"}, name="api_list_list")
     */
    public function listAction(Request $request, PaginatorInterface $paginator)
    {
        if (!$this->getUser()) {
            return new JsonHttpException(404, 'Lists not found');
        }

        $startId = $request->query->has('startId') && $request->query->get('startId') > 0 ? $request->query->get('startId') : 1;
        $listsNumber = $request->query->has('listsNumber') && $request->query->get('listsNumber') > 0 ? $request->query->get('listsNumber') : 5;

        $lists = $this->getDoctrine()->getRepository(ItemList::class)->findAllByUser($this->getUser(), $startId);

        return $this->json($paginator->paginate($lists, 1, $listsNumber));
    }

    /**
     * @Route("/api/lists/{id}", methods={"DELETE"}, name="api_list_delete")
     */
    public function deleteAction(Request $request, ItemList $itemList)
    {
        $this->denyAccessUnlessGranted(ItemListVoter::DELETE, $itemList);

        $this->getDoctrine()->getManager()->remove($itemList);
        $this->getDoctrine()->getManager()->flush();

        return $this->json('ok');
    }

    /**
     * @Route("/api/lists/{id}", methods={"PUT"}, name="api_list_edit")
     */
    public function editAction(Request $request, ItemList $itemList, LabelService $labelService)
    {
        $this->denyAccessUnlessGranted(ItemListVoter::EDIT, $itemList);

        /* @var ItemList $newItemList */
        $newItemList = $this->serializer->deserialize($request->getContent(), ItemList::class, 'json');
        $newTitle = $newItemList->getTitle();
        $newLabels = $newItemList->getLabels();

        $itemList->setTitle($newTitle);
        $this->validateService->validate($itemList);
        $labelService->syncLabels($newLabels, $itemList);
        $this->validateService->validate($itemList->getLabels());

        $this->getDoctrine()->getManager()->flush();

        return $this->json('ok');
    }

    /**
     * @Route("/api/lists/{id}", methods={"GET"}, name="api_list_show")
     */
    public function showAction(Request $request, ItemList $itemList)
    {
        $this->denyAccessUnlessGranted(ItemListVoter::VIEW, $itemList);

        return $this->json($itemList, 200, [], [AbstractNormalizer::GROUPS => [ItemListNormalizer::GROUP_DETAILS]]);
    }
}
