##Allowed methods for requests from the browser
###Chat requests:
    - register
       - message{userInfo, broadcast_statistics} (to members)
    
    - initEventDiscussion
       - message{comments, users} (to self)
    - commentList
       - message{comments} (response to self)
        
    - personalMessageList
       - message{personalMessages} (to self)
    - personalMessageCreated [old name '~~personalSendMessage~~']
       - message{personalMessageCreated} (to target user)
       - message{personalMessageResult} (to self) [old name '~~personalMessageInfo~~']
    
    - userBlock
       - message{chatUserBlockedNotice} (to target user)
       - message{chatUserBlockedNotice} (to all speakers, organizers, company users)
       - message{chatUserBlockResult} (to self)
    - userUnblock
       - message{chatUserUnblockedNotice} (to target user)
       - message{chatUserUnblockedNotice} (to all speakers, organizers, company users)
       - message{chatUserUnblockResult} (to self)
 
    - getUsersOnline
       - message{broadcast_statistics} (to self)

##Responses to the browser by triggering from web-socket-client(backend side)
###Chat responses:
    url: /api/events/add-comment
       - commentCreated (to members) [old name '~~commentNew~~']
    url: /api/events/remove-comment
       - commentDeleted{id} (to members) [old name '~~deletedComment~~']
    url: /api/events/{set|unset}-like-comment
       - commentVoted{comment_id, event_id, like_type} (to members) [old name '~~voted~~']
    
    url: /api/events/user-speaker-assign
      - speakerAssigned{user_id} (to members)
    url: /api/events/user-speaker-revoke
      - speakerRevoked{user_id} (to members)
       
###Notice responses:
    url: /api/events/{create|approve|reject}-meeting
       - meetingNotice{text, event_type, meeting_id} (to one of the meeting participants, refer by 'event_type') [old name '~~notice~~']

