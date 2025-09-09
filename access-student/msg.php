<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/connect/db.php');

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Location: /login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Chat</title>
    <style>
        body {
            font-family: Arial;
            display: flex;
            height: 100vh;
            margin: 0;
        }

        .sidebar {
            width: 250px;
            background: #f5f5f5;
            padding: 15px;
            border-right: 1px solid #ccc;
            overflow-y: auto;
        }

        .chat-container {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .user,
        .group {
            cursor: pointer;
            padding: 10px;
            margin-bottom: 5px;
            background: #fff;
            border-radius: 5px;
            position: relative;
        }

        .user:hover,
        .group:hover {
            background: #e0e0e0;
        }

        .active-chat {
            background: #d0ebff !important;
        }

        .badge {
            background: red;
            color: white;
            font-size: 12px;
            padding: 2px 6px;
            border-radius: 12px;
            position: absolute;
            top: 8px;
            right: 10px;
        }

        #chat-messages {
            flex: 1;
            padding: 10px;
            overflow-y: auto;
            background: #f9f9f9;
            border-bottom: 1px solid #ccc;
        }

        #chat-form {
            display: flex;
            padding: 10px;
        }

        #chat-input {
            flex: 1;
            padding: 10px;
        }

        #chat-submit {
            padding: 10px 15px;
        }

        #typing-indicator {
            font-size: 13px;
            color: #555;
            padding-left: 12px;
            height: 20px;
        }

        #search-results {
            position: absolute;
            top: 88px;
            left: 15px;
            width: 220px;
            background: #fff;
            border: 1px solid #ccc;
            border-radius: 5px;
            z-index: 999;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
            max-height: 250px;
            overflow-y: auto;
            display: none;
        }

        #search-results .user {
            padding: 8px;
            border-bottom: 1px solid #eee;
        }

        #search-results .user:hover {
            background-color: #f1f1f1;
        }

        .create-group-btn {
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 8px 14px;
            font-size: 14px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .create-group-btn:hover {
            background-color: #0056b3;
        }

        .group-options {
            float: right;
            font-size: 14px;
            cursor: pointer;
        }

        .group-actions {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
            margin-top: 10px;
        }

        .group-actions button {
            padding: 6px 10px;
            font-size: 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .group-actions button:hover {
            opacity: 0.9;
        }
    </style>
</head>

<body>
    <div class="sidebar">
        <h3>Private Chats</h3>
        <input type="text" id="user-search" placeholder="Search users..." style="width: 100%; padding: 8px; margin-bottom: 10px;">

        <div id="search-results" style="display: none;"></div>
        <div id="private-chats">
            <?php
            $stmt = $conn->prepare("
        SELECT u.id, u.first_name, u.last_name, u.username, u.profile_pic
        FROM users u
        WHERE u.id != ?
        AND EXISTS (
            SELECT 1 FROM messages m 
            WHERE (m.sender_id = ? AND m.receiver_id = u.id) 
               OR (m.sender_id = u.id AND m.receiver_id = ?)
        )
    ");
            $stmt->bind_param("iii", $user_id, $user_id, $user_id);
            $stmt->execute();
            $users = $stmt->get_result();
            while ($user = $users->fetch_assoc()):
            ?>
                <div class="user" data-user-id="<?= $user['id'] ?>" onclick="openChat(<?= $user['id'] ?>, null)">
                    <img src="/uploads/profile_pics/<?= htmlspecialchars($user['profile_pic'] ?? 'default.png') ?>" width="24" style="border-radius:50%; vertical-align:middle;">
                    <?= htmlspecialchars($user['username']) ?>
                    <span class="badge" style="display:none;"></span>
                </div>
            <?php endwhile; ?>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px;">
            <h3 style="margin: 0;">Groups</h3>
            <button onclick="openCreateGroupModal()" class="create-group-btn">Create Group</button>
        </div>

        <!-- Modal should stay outside so it's not affected by layout -->
        <div id="create-group-modal" style="display:none; position:fixed; top:20%; left:50%; transform:translateX(-50%); background:#fff; padding:20px; border:1px solid #ccc; border-radius:8px; box-shadow:0 5px 15px rgba(0,0,0,0.3); z-index:999;">
            <h4>Create Group</h4>
            <input type="text" id="group-name" placeholder="Group Name" style="width:100%; margin-bottom:10px;">

            <input type="text" id="user-search-input" placeholder="Search users..." style="width:100%; margin-bottom:8px; padding:5px; border:1px solid #ccc; border-radius:4px;">

            <div id="user-list" style="max-height:200px; overflow-y:auto; border:1px solid #ccc; padding:5px; border-radius:4px;"></div>

            <button onclick="submitGroup()">Create</button>
            <button onclick="closeCreateGroupModal()">Cancel</button>
        </div>


        <div id="group-chats">
            <?php
            $stmt = $conn->prepare("
            SELECT cg.id, cg.name 
            FROM chat_groups cg
            JOIN chat_group_members cgm ON cg.id = cgm.group_id
            WHERE cgm.user_id = ?
        ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $groups = $stmt->get_result();
            while ($group = $groups->fetch_assoc()):
            ?>
                <div class="group"
                    data-group-id="<?= $group['id'] ?>"
                    onclick="openChat(null, <?= $group['id'] ?>, '<?= htmlspecialchars(addslashes($group['name'])) ?>')">
                    🧑‍🤝‍🧑 <?= htmlspecialchars($group['name']) ?>
                    <span class="badge" style="display:none;"></span>
                </div>


            <?php endwhile; ?>
        </div>
    </div>

    <div class="chat-container">
        <div id="chat-header" style="padding: 10px; border-bottom: 1px solid #ccc; font-weight: bold; background: #fff; display: flex; align-items: center; justify-content: space-between;">
            <span id="chat-title" style="flex: 1;">Select a chat</span>
            <button id="group-options-btn" onclick="openGroupActions()" title="Group Settings"
                style="display: none; background: none; border: none; font-size: 18px; cursor: pointer;">⚙️</button>
            <button id="view-members-btn" onclick="openGroupMembersModal()" title="Group Members"
                style="display: none; background: none; border: none; font-size: 18px; cursor: pointer;">👥</button>

        </div>



        <div id="chat-messages">
            <p>Select a chat to view messages.</p>
        </div>

        <div id="typing-indicator"></div>

        <form id="chat-form" onsubmit="sendMessage(event)">
            <input type="text" id="chat-input" placeholder="Type your message..." autocomplete="off" oninput="sendTyping()" />
            <button type="submit" id="chat-submit">Send</button>
        </form>
    </div>

    <div id="group-action-modal" style="display:none; position:fixed; top:25%; left:50%; transform:translateX(-50%); background:#fff; padding:20px; border:1px solid #ccc; border-radius:8px; z-index:1000; box-shadow:0 5px 15px rgba(0,0,0,0.3);">
        <h4>Group Settings</h4>
        <input type="hidden" id="action-group-id">
        <input type="text" id="rename-group-input" placeholder="Rename group" style="width:100%; margin-bottom:10px;">
        <div class="group-actions">
            <button style="background:#007bff; color:#fff" onclick="renameGroup()">Rename</button>
            <button style="background:#28a745; color:#fff" onclick="addUsersToGroup()">Add Users</button>
            <button style="background:#dc3545; color:#fff" onclick="leaveGroup()">Leave Group</button>
            <button style="background:#6c757d; color:#fff" onclick="closeGroupActions()">Cancel</button>
        </div>
    </div>

    <div id="add-users-modal" style="display:none; position:fixed; top:25%; left:50%; transform:translateX(-50%); background:#fff; padding:20px; border:1px solid #ccc; border-radius:8px; box-shadow:0 5px 15px rgba(0,0,0,0.3); z-index:1000;">
        <h4>Add Users to Group</h4>
        <input type="text" id="add-user-search" placeholder="Search users..." style="width:100%; margin-bottom:10px; padding:5px; border:1px solid #ccc; border-radius:4px;">
        <div id="add-user-list" style="max-height:200px; overflow-y:auto; border:1px solid #ccc; padding:5px; border-radius:4px;"></div>

        <div style="margin-top: 10px; display: flex; justify-content: flex-end; gap: 8px;">
            <button onclick="submitAddUsers()" style="background:#007bff; color:white;">Add Selected</button>
            <button onclick="closeAddUsersModal()">Cancel</button>
        </div>
    </div>

    <div id="group-members-modal" style="display:none; position:fixed; top:25%; left:50%; transform:translateX(-50%);
    background:#fff; padding:20px; border:1px solid #ccc; border-radius:8px; z-index:1000; width:300px;">
        <h4>Group Members</h4>
        <ul id="group-members-list" style="list-style: none; padding: 0; max-height: 250px; overflow-y: auto;"></ul>
        <button onclick="closeGroupMembersModal()">Close</button>
    </div>



    <script>
        let selectedUserId = null;
        let selectedGroupId = null;
        let typingTimeout = null;

        function highlightActiveChat() {
            document.querySelectorAll('.user, .group').forEach(el => el.classList.remove('active-chat'));
            if (selectedUserId) {
                document.querySelector(`.user[data-user-id="${selectedUserId}"]`)?.classList.add('active-chat');
            } else if (selectedGroupId) {
                document.querySelector(`.group[data-group-id="${selectedGroupId}"]`)?.classList.add('active-chat');
            }
        }

        function openChat(userId, groupId, groupName = null) {
            selectedUserId = userId;
            selectedGroupId = groupId;

            localStorage.setItem('chat_userId', userId ?? '');
            localStorage.setItem('chat_groupId', groupId ?? '');

            highlightActiveChat();

            const chatTitleEl = document.getElementById('chat-title');
            const settingsBtn = document.getElementById('group-options-btn');
            const membersBtn = document.getElementById('view-members-btn');

            if (groupId) {
                settingsBtn.style.display = 'inline-block';
                membersBtn.style.display = 'inline-block';

                if (groupName) {
                    chatTitleEl.innerText = groupName;
                    settingsBtn.setAttribute('onclick', `openGroupActions(${groupId}, "${groupName.replace(/"/g, '\\"')}")`);
                } else {
                    chatTitleEl.innerText = 'Group Chat';
                    settingsBtn.setAttribute('onclick', `openGroupActions(${groupId}, "")`);
                }

                membersBtn.setAttribute('onclick', `openGroupMembersModal()`);

            } else {
                chatTitleEl.innerText = 'Private Chat';
                settingsBtn.style.display = 'none';
                membersBtn.style.display = 'none';
                settingsBtn.removeAttribute('onclick');
                membersBtn.removeAttribute('onclick');
            }

            // Still fetch title from backend in case groupName is null or outdated
            fetch('chat/get_chat_title', {
                method: 'POST',
                body: new URLSearchParams({
                    userId,
                    groupId
                })
            }).then(res => res.text()).then(title => {
                chatTitleEl.innerText = title;
            });

            // Load chat messages
            fetch('chat/fetch_messages', {
                method: 'POST',
                body: new URLSearchParams({
                    userId,
                    groupId
                })
            }).then(res => res.text()).then(html => {
                const msgBox = document.getElementById('chat-messages');
                msgBox.innerHTML = html;
                msgBox.scrollTop = msgBox.scrollHeight;
            });
        }




        function sendMessage(e) {
            e.preventDefault();
            const message = document.getElementById('chat-input').value;
            if (!message.trim()) return;

            const formData = new URLSearchParams();
            formData.append('message', message);

            if (selectedUserId) {
                formData.append('userId', selectedUserId);
            } else if (selectedGroupId) {
                formData.append('groupId', selectedGroupId);
            }

            fetch('chat/send_message', {
                method: 'POST',
                body: formData
            }).then(() => {
                document.getElementById('chat-input').value = '';
                openChat(selectedUserId, selectedGroupId);
            });
        }


        function sendTyping() {
            if (typingTimeout) clearTimeout(typingTimeout);

            fetch('chat/typing_status', {
                method: 'POST',
                body: new URLSearchParams({
                    userId: selectedUserId,
                    groupId: selectedGroupId
                })
            });

            typingTimeout = setTimeout(() => {
                // clear typing status after delay
            }, 3000);
        }

        function checkTypingStatus() {
            fetch('chat/check_typing_status', {
                method: 'POST',
                body: new URLSearchParams({
                    userId: selectedUserId,
                    groupId: selectedGroupId
                })
            }).then(res => res.text()).then(txt => {
                document.getElementById('typing-indicator').innerText = txt === 'typing' ? 'Typing…' : '';
            });
        }

        function checkNotifications() {
            fetch('chat/check_notifications')
                .then(res => res.json())
                .then(data => {
                    document.querySelectorAll('.user, .group').forEach(el => {
                        const badge = el.querySelector('.badge');
                        const isGroup = el.classList.contains('group');
                        const id = isGroup ? el.getAttribute('data-group-id') : el.getAttribute('data-user-id');
                        const key = isGroup ? `g-${id}` : id;

                        const count = data[key] || 0;

                        const isCurrentChat =
                            (isGroup && selectedGroupId == id) ||
                            (!isGroup && selectedUserId == id);

                        if (count > 0 && !isCurrentChat) {
                            badge.innerText = count;
                            badge.style.display = 'inline-block';
                        } else {
                            badge.style.display = 'none';
                        }
                    });
                });
        }


        function openCreateGroupModal() {
            document.getElementById('create-group-modal').style.display = 'block';
            document.getElementById('user-list').innerHTML = '';

            fetch('chat/get_all_users')
                .then(res => res.json())
                .then(users => {
                    const list = document.getElementById('user-list');
                    list.innerHTML = '';
                    users.forEach(user => {
                        const div = document.createElement('div');
                        div.innerHTML = `<label><input type="checkbox" value="${user.id}"> ${user.first_name} ${user.last_name} (@${user.username})</label>`;
                        list.appendChild(div);
                    });
                });
        }

        function closeCreateGroupModal() {
            document.getElementById('create-group-modal').style.display = 'none';
        }

        function submitGroup() {
            const name = document.getElementById('group-name').value.trim();
            const checked = [...document.querySelectorAll('#user-list input:checked')].map(cb => cb.value);
            if (!name || checked.length === 0) return alert('Please enter a name and select at least one user.');

            fetch('chat/create_group', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        name,
                        members: checked
                    })
                })
                .then(res => res.json())
                .then(resp => {
                    if (resp.success) {
                        closeCreateGroupModal();
                        location.reload();
                    } else {
                        alert(resp.message || 'Failed to create group.');
                    }
                });
        }

        function openGroupActions(groupId, groupName) {
            document.getElementById('action-group-id').value = groupId;
            document.getElementById('rename-group-input').value = groupName;
            document.getElementById('group-action-modal').style.display = 'block';
        }

        function closeGroupActions() {
            document.getElementById('group-action-modal').style.display = 'none';
        }

        function renameGroup() {
            const groupId = document.getElementById('action-group-id').value;
            const newName = document.getElementById('rename-group-input').value.trim();
            if (!newName) return alert('Enter a group name.');

            fetch('chat/rename_group', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    group_id: groupId,
                    new_name: newName
                })
            }).then(res => res.json()).then(resp => {
                if (resp.success) location.reload();
                else alert('Rename failed');
            });
        }

        function leaveGroup() {
            const groupId = document.getElementById('action-group-id').value;
            if (!confirm('Leave this group?')) return;

            fetch('chat/leave_group', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    group_id: groupId
                })
            }).then(res => res.json()).then(resp => {
                if (resp.success) location.reload();
                else alert('Failed to leave group');
            });
        }

        let selectedUserIds = new Set();

        function addUsersToGroup() {
            selectedUserIds = new Set();
            document.getElementById('add-users-modal').style.display = 'block';
            document.getElementById('add-user-search').value = '';
            document.getElementById('add-user-list').innerHTML = '';
        }

        function closeAddUsersModal() {
            document.getElementById('add-users-modal').style.display = 'none';
        }

        document.getElementById('add-user-search').addEventListener('input', function() {
            const query = this.value.trim();
            const groupId = document.getElementById('action-group-id').value;
            const list = document.getElementById('add-user-list');

            if (query.length < 2) {
                list.innerHTML = '<small>Type at least 2 characters to search.</small>';
                return;
            }

            fetch('chat/get_user?q=' + encodeURIComponent(query) + '&group_id=' + encodeURIComponent(groupId))
                .then(res => res.json())
                .then(users => {
                    list.innerHTML = '';
                    if (users.length === 0) {
                        list.innerHTML = '<small>No users found.</small>';
                        return;
                    }

                    users.forEach(user => {
                        const isAlreadyJoined = user.joined;
                        const div = document.createElement('div');
                        const checkboxId = `user-checkbox-${user.id}`;
                        div.innerHTML = `
                    <label for="${checkboxId}">
                        <input type="checkbox" id="${checkboxId}" value="${user.id}" 
                            ${isAlreadyJoined ? 'disabled' : ''}>
                        ${user.first_name} ${user.last_name} (@${user.username})
                        ${isAlreadyJoined ? '<span style="color:gray;font-size:0.9em;">(Already in group)</span>' : ''}
                    </label>
                `;
                        list.appendChild(div);

                        if (!isAlreadyJoined) {
                            div.querySelector('input').addEventListener('change', (e) => {
                                const id = parseInt(e.target.value);
                                if (e.target.checked) {
                                    selectedUserIds.add(id);
                                    e.target.disabled = true;
                                }
                            });
                        }
                    });
                });
        });

        function submitAddUsers() {
            const groupId = document.getElementById('action-group-id').value;
            const ids = Array.from(selectedUserIds);

            if (ids.length === 0) {
                alert('Select at least one user.');
                return;
            }

            fetch('chat/add_users_to_group', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        group_id: groupId,
                        user_ids: ids
                    })
                })
                .then(res => res.json())
                .then(resp => {
                    if (resp.success) {
                        let msg = '';
                        if (resp.added?.length) {
                            msg += `✅ Added: ${resp.added.join(', ')}\n`;
                        }
                        if (resp.already_in_group?.length) {
                            msg += `ℹ️ Already in group: ${resp.already_in_group.join(', ')}`;
                        }

                        alert(msg || 'No changes made.');
                        closeAddUsersModal();
                        closeGroupActions();
                        location.reload();
                    } else {
                        alert(resp.message || 'Failed to add users.');
                    }
                });
        }



        function openGroupMembersModal() {
            if (!selectedGroupId) return;

            fetch('chat/get_group_members', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'group_id=' + selectedGroupId
                })
                .then(res => res.json())
                .then(data => {
                    const list = document.getElementById('group-members-list');
                    list.innerHTML = '';
                    data.members.forEach(member => {
                        const li = document.createElement('li');
                        li.style.marginBottom = '8px';

                        let text = `${member.full_name} (@${member.username})`;
                        if (member.can_kick) {
                            const btn = document.createElement('button');
                            btn.textContent = 'Kick';
                            btn.style.marginLeft = '10px';
                            btn.style.fontSize = '12px';
                            btn.onclick = () => kickMember(member.id);
                            li.append(text, btn);
                        } else {
                            li.textContent = text;
                        }

                        list.appendChild(li);
                    });

                    document.getElementById('group-members-modal').style.display = 'block';
                });
        }

        function closeGroupMembersModal() {
            document.getElementById('group-members-modal').style.display = 'none';
        }

        function kickMember(userId) {
            if (!confirm('Kick this user from the group?')) return;

            fetch('chat/kick_user_from_group', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        group_id: selectedGroupId,
                        user_id: userId
                    })
                })
                .then(res => res.json())
                .then(resp => {
                    if (resp.success) {
                        alert('User removed.');
                        openGroupMembersModal(); // Refresh list
                    } else {
                        alert(resp.message || 'Failed to remove user.');
                    }
                });
        }





        document.getElementById('user-search').addEventListener('input', function() {
            const query = this.value.trim();
            const resultsBox = document.getElementById('search-results');
            if (query.length < 2) {
                resultsBox.style.display = 'none';
                resultsBox.innerHTML = '';
                return;
            }

            fetch('chat/search_all_chats?q=' + encodeURIComponent(query))
                .then(res => res.json())
                .then(results => {
                    resultsBox.innerHTML = '';
                    resultsBox.style.display = 'block';

                    if (results.length === 0) {
                        const div = document.createElement('div');
                        div.className = 'user';
                        div.innerText = 'No search found';
                        div.style.cursor = 'default';
                        div.style.color = '#888';
                        resultsBox.appendChild(div);
                        return;
                    }

                    results.forEach(item => {
                        const div = document.createElement('div');
                        if (item.type === 'user') {
                            div.className = 'user';
                            div.dataset.userId = item.id;
                            div.innerHTML = `
                        <img src="/uploads/profile_pics/${item.profile_pic || 'default.png'}" width="24" style="border-radius:50%; vertical-align:middle;">
                        ${item.first_name} ${item.last_name} <small style="color:gray;">@${item.username}</small>
                    `;
                            div.onclick = () => {
                                openChat(item.id, null);
                                resultsBox.style.display = 'none';
                                document.getElementById('user-search').value = '';
                            };
                        } else if (item.type === 'group') {
                            div.className = 'group';
                            div.dataset.groupId = item.id;
                            div.innerHTML = `🧑‍🤝‍🧑 ${item.name}`;
                            div.onclick = () => {
                                openChat(null, item.id);
                                resultsBox.style.display = 'none';
                                document.getElementById('user-search').value = '';
                            };
                        }
                        resultsBox.appendChild(div);
                    });
                });
        });



        // Hide search results if user clicks outside
        document.addEventListener('click', function(e) {
            const searchInput = document.getElementById('user-search');
            const resultsBox = document.getElementById('search-results');

            if (!searchInput.contains(e.target) && !resultsBox.contains(e.target)) {
                resultsBox.style.display = 'none';
            }
        });

        document.getElementById('user-search-input').addEventListener('input', function() {
            const query = this.value.trim();
            const list = document.getElementById('user-list');

            if (query.length < 2) {
                list.innerHTML = '<div style="color: #888;">Type at least 2 characters to search.</div>';
                return;
            }

            fetch('chat/search_all_chats?q=' + encodeURIComponent(query))
                .then(res => res.json())
                .then(users => {
                    list.innerHTML = '';
                    if (users.length === 0) {
                        list.innerHTML = '<div style="color: #888;">No users found.</div>';
                        return;
                    }

                    users.forEach(user => {
                        const div = document.createElement('div');
                        div.innerHTML = `
                    <label>
                        <input type="checkbox" value="${user.id}">
                        ${user.first_name} ${user.last_name} <small style="color:gray;">@${user.username}</small>
                    </label>`;
                        list.appendChild(div);
                    });
                });
        });




        // Load previous chat on page load
        window.addEventListener('load', () => {
            const userId = localStorage.getItem('chat_userId');
            const groupId = localStorage.getItem('chat_groupId');

            if (groupId) {
                // Try to get group name from DOM
                const groupEl = document.querySelector(`.group[data-group-id="${groupId}"]`);
                const name = groupEl?.textContent?.trim().replace(/^🧑‍🤝‍🧑\s*/, '') || 'Group Chat';
                openChat(null, groupId, name);
            } else if (userId) {
                openChat(userId, null);
            }
        });

        // Refresh chat + indicators
        setInterval(() => {
            if (selectedUserId !== null || selectedGroupId !== null) {
                const chatTitle = document.getElementById('chat-title')?.innerText || '';
                openChat(selectedUserId, selectedGroupId, chatTitle); // pass title as fallback for group name
                checkTypingStatus();
            }
            checkNotifications();
        }, 3000);
    </script>
</body>

</html>