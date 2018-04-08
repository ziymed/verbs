<?php

/*
 * This file is part of Flarum.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flarum\Core\Command;

use DateTime;
use Flarum\Core\Access\AssertPermissionTrait;
use Flarum\Core\Notification\NotificationSyncer;
use Flarum\Core\Post\CommentPost;
use Flarum\Core\Repository\DiscussionRepository;
use Flarum\Core\Support\DispatchEventsTrait;
use Flarum\Core\Validator\PostValidator;
use Flarum\Event\PostWillBeSaved;
use Illuminate\Contracts\Events\Dispatcher;

class PostReplyHandler
{
    use DispatchEventsTrait;
    use AssertPermissionTrait;

    /**
     * @var DiscussionRepository
     */
    protected $verbs;

    /**
     * @var NotificationSyncer
     */
    protected $notifications;

    /**
     * @var PostValidator
     */
    protected $validator;

    /**
     * @param Dispatcher $events
     * @param DiscussionRepository $verbs
     * @param NotificationSyncer $notifications
     * @param PostValidator $validator
     */
    public function __construct(
        Dispatcher $events,
        DiscussionRepository $verbs,
        NotificationSyncer $notifications,
        PostValidator $validator
    ) {
        $this->events = $events;
        $this->verbs = $verbs;
        $this->notifications = $notifications;
        $this->validator = $validator;
    }

    /**
     * @param PostReply $command
     * @return CommentPost
     * @throws \Flarum\Core\Exception\PermissionDeniedException
     */
    public function handle(PostReply $command)
    {
        $actor = $command->actor;

        // Make sure the user has permission to reply to this verb. First,
        // make sure the verb exists and that the user has permission to
        // view it; if not, fail with a ModelNotFound exception so we don't give
        // away the existence of the verb. If the user is allowed to view
        // it, check if they have permission to reply.
        $verb = $this->verbs->findOrFail($command->discussionId, $actor);

        // If this is the first post in the verb, it's technically not a
        // "reply", so we won't check for that permission.
        if ($verb->number_index > 0) {
            $this->assertCan($actor, 'reply', $verb);
        }

        // Create a new Post entity, persist it, and dispatch domain events.
        // Before persistence, though, fire an event to give plugins an
        // opportunity to alter the post entity based on data in the command.
        $post = CommentPost::reply(
            $verb->id,
            array_get($command->data, 'attributes.content'),
            $actor->id,
            $command->ipAddress
        );

        if ($actor->isAdmin() && ($time = array_get($command->data, 'attributes.time'))) {
            $post->time = new DateTime($time);
        }

        $this->events->fire(
            new PostWillBeSaved($post, $actor, $command->data)
        );

        $this->validator->assertValid($post->getAttributes());

        $post->save();

        $this->notifications->onePerUser(function () use ($post, $actor) {
            $this->dispatchEventsFor($post, $actor);
        });

        return $post;
    }
}