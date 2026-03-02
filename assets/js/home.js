// Home Page JavaScript
let currentUser = null;
let posts = [];
let notifications = [];
const READ_NOTIFS_KEY = 'read_notif_ids';
let selectedMediaFiles = [];
let currentView = 'home'; // 'home' | 'profile'
let currentFilterUserId = null;
let viewedProfile = null; // profile currently being viewed (self or another user)
let announcementsOnly = false; // true when viewing only announcements
let profileStats = { postCount: 0, followerCount: 0, followingCount: 0 };
let isNewUser = false;
let focusPostId = null; // post to scroll to when coming from a notification
let profileStatsRequestSeq = 0; // prevent stale async profile stats overwriting latest values
let currentUserRole = 'student';
let hasShownNewUserGuidePopup = false;
let isNewUserGuideDismissedForAccount = false;
const MAX_TOTAL_UPLOAD_MB = 38;
let mediaViewerState = { items: [], index: 0 };
let editPostState = {
    postId: null,
    existingMedia: [],
    newMedia: [],
    nextNewId: 1
};

function dismissNewUserGuideForever() {
    hasShownNewUserGuidePopup = true;
    isNewUserGuideDismissedForAccount = true;
    fetch('api/dismiss_new_user_guide.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: currentUser.id })
    }).catch(() => {});
}

function isAdminRole() {
    return currentUserRole === 'admin' || !!(currentUser && currentUser.isAdmin);
}

function isStaffRole() {
    return currentUserRole === 'faculty';
}

function isModeratorRole() {
    return isAdminRole() || isStaffRole();
}
 
document.addEventListener('DOMContentLoaded', async function() {
    // Check authentication
    const userData = localStorage.getItem('user');
    if (!userData) {
        window.location.replace('index.html');
        return;
    }

    currentUser = JSON.parse(userData);
    currentUserRole = currentUser?.accountType || (currentUser?.isAdmin ? 'admin' : 'student');
    
    // Check account status (banned/locked)
    await checkAccountStatus();
    
    initializePage();
    viewedProfile = { ...currentUser };
    updateProfileSidebar(viewedProfile);
    updateStoriesVisibility();
    await loadMyProfileStats();
    loadPosts();
    loadNotifications();
    loadStories();
    setupEventListeners();

    // Show app after auth check to avoid UI flashing/blinking.
    document.body.classList.remove('app-hidden');
});

async function checkAccountStatus() {
    try {
        const response = await fetch(`api/get_profile.php?user_id=${currentUser.id}&viewer_id=${currentUser.id}`);
        const data = await response.json();
        
        if (data.success && data.profile) {
            // Check if account is locked
            const statusResponse = await fetch(`api/check_user_status.php?user_id=${currentUser.id}`);
            const statusData = await statusResponse.json();
            
            if (statusData.success) {
                if (statusData.locked) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Account Locked',
                        html: `
                            <p>Your account has been locked due to sensitive and not reliable content.</p>
                            ${statusData.lock_until ? `<p><small>Lock expires: ${new Date(statusData.lock_until).toLocaleString()}</small></p>` : ''}
                        `,
                        confirmButtonText: 'OK',
                        allowOutsideClick: false,
                        allowEscapeKey: false
                    }).then(() => {
                        localStorage.removeItem('user');
                        window.location.href = 'index.html';
                    });
                    return;
                }
                
                if (statusData.banned) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Account Banned',
                        html: `
                            <p>Your account has been banned.</p>
                            ${statusData.ban_reason ? `<p><strong>Reason:</strong> ${statusData.ban_reason}</p>` : ''}
                            ${statusData.banned_until ? `<p><small>Ban expires: ${new Date(statusData.banned_until).toLocaleString()}</small></p>` : ''}
                        `,
                        confirmButtonText: 'OK',
                        allowOutsideClick: false,
                        allowEscapeKey: false
                    }).then(() => {
                        localStorage.removeItem('user');
                        window.location.href = 'index.html';
                    });
                    return;
                }
            }
        }
    } catch (error) {
        console.error('Error checking account status:', error);
    }
}

function initializePage() {
    // Set user profile info
    document.getElementById('profileName').textContent = currentUser.name || currentUser.username;
    const profileUsernameEl = document.getElementById('profileUsername');
    if (profileUsernameEl) {
        profileUsernameEl.textContent = '';
        profileUsernameEl.style.display = 'none';
    }

    syncAvatars(currentUser.avatar);
    setCoverPhoto(currentUser.cover_photo);
    
    // Show announcement toggle for faculty and admin
    if (currentUser.accountType === 'faculty' || currentUser.accountType === 'admin' || currentUser.isAdmin) {
        document.getElementById('announcementToggle').style.display = 'block';
    }
    
    // Show privacy selector for students (and faculty for regular posts); hide when announcement is checked
    const privacyRow = document.getElementById('privacyRow');
    const isAnn = document.getElementById('isAnnouncement');
    if (privacyRow) {
        privacyRow.style.display = (currentUser.accountType === 'student' || currentUser.accountType === 'faculty' || currentUser.accountType === 'admin') && !(isAnn && isAnn.checked) ? 'flex' : 'none';
    }
    if (isAnn) isAnn.addEventListener('change', function() {
        const pr = document.getElementById('privacyRow');
        if (pr) pr.style.display = this.checked ? 'none' : 'flex';
    });
    
    // Show moderation menu item for admins and staff
    if (isModeratorRole()) {
        document.getElementById('adminMenuItem').style.display = 'block';
    }

    // Staff moderation scope: student activities + accounts + reports (no announcements/logs)
    if (isStaffRole()) {
        const tabIds = ['announcements-tab', 'logs-tab'];
        const paneIds = ['announcements', 'logs'];
        tabIds.forEach((id) => {
            const tab = document.getElementById(id);
            if (tab && tab.parentElement) tab.parentElement.style.display = 'none';
        });
        paneIds.forEach((id) => {
            const pane = document.getElementById(id);
            if (pane) pane.style.display = 'none';
        });
    }
}

function setupEventListeners() {
    // Navigation (Home/Profile/etc.)
    document.querySelectorAll('[data-page]').forEach(el => {
        el.addEventListener('click', (e) => {
            e.preventDefault();
            const page = el.getAttribute('data-page');
            if (page) navigateTo(page);
        });
    });

    // Create post button
    document.getElementById('createPostBtn').addEventListener('click', createPost);
    document.getElementById('discardPostDraftBtn')?.addEventListener('click', discardCreatePostDraft);
    document.getElementById('postContent')?.addEventListener('input', updateCreatePostDiscardVisibility);
    
    // View Announcements button (toggle announcements-only feed)
    const viewAnnBtn = document.getElementById('viewAnnouncementsBtn');
    if (viewAnnBtn) {
        viewAnnBtn.addEventListener('click', function() {
            announcementsOnly = !announcementsOnly;
            viewAnnBtn.querySelector('span').textContent = announcementsOnly ? 'Back to Feed' : 'Announcements';
            viewAnnBtn.querySelector('i').className = announcementsOnly ? 'fas fa-arrow-left' : 'fas fa-bullhorn';
            loadPosts();
        });
    }
    
    // Media preview
    document.getElementById('postMedia').addEventListener('change', handleMediaPreview);

    // Change avatar
    document.getElementById('changeAvatarBtn')?.addEventListener('click', () => {
        document.getElementById('avatarInput')?.click();
    });
    document.getElementById('avatarInput')?.addEventListener('change', handleAvatarChange);

    // Edit profile
    document.getElementById('editProfileBtn')?.addEventListener('click', openProfileEditor);
    
    // Notification panel
    document.getElementById('notificationBtn').addEventListener('click', toggleNotificationPanel);
    document.getElementById('closeNotifications').addEventListener('click', toggleNotificationPanel);
    
    // Mark all notifications as read/unread
    document.getElementById('markAllReadBtn')?.addEventListener('click', () => markAllNotifications(true));
    document.getElementById('markAllUnreadBtn')?.addEventListener('click', () => markAllNotifications(false));
    
    // Logout
    document.getElementById('logoutBtn').addEventListener('click', handleLogout);
    
    // Burger menu
    const burgerBtn = document.getElementById('navBurger');
    const burgerDrawer = document.getElementById('burgerDrawer');
    const burgerOverlay = document.getElementById('burgerOverlay');
    const burgerCloseBtn = document.getElementById('burgerClose');
    if (burgerBtn) burgerBtn.addEventListener('click', openBurgerMenu);
    if (burgerCloseBtn) burgerCloseBtn.addEventListener('click', closeBurgerMenu);
    if (burgerOverlay) burgerOverlay.addEventListener('click', closeBurgerMenu);
    document.querySelectorAll('.burger-menu-item[data-page="profile"]').forEach(el => {
        el.addEventListener('click', (e) => { e.preventDefault(); closeBurgerMenu(); navigateTo('profile'); });
    });
    const burgerEditProfile = document.getElementById('burgerEditProfile');
    if (burgerEditProfile) burgerEditProfile.addEventListener('click', (e) => { e.preventDefault(); closeBurgerMenu(); openProfileEditor(); });
    const burgerChangePassword = document.getElementById('burgerChangePassword');
    if (burgerChangePassword) burgerChangePassword.addEventListener('click', (e) => { e.preventDefault(); closeBurgerMenu(); openChangePasswordModal(); });
    const burgerChangeUsername = document.getElementById('burgerChangeUsername');
    if (burgerChangeUsername) burgerChangeUsername.addEventListener('click', (e) => { e.preventDefault(); closeBurgerMenu(); openChangeUsernameModal(); });
    const burgerPrivacy = document.getElementById('burgerPrivacySettings');
    if (burgerPrivacy) burgerPrivacy.addEventListener('click', (e) => { e.preventDefault(); closeBurgerMenu(); openPrivacySettingsModal(); });
    const burgerBlocked = document.getElementById('burgerBlockedUsers');
    if (burgerBlocked) burgerBlocked.addEventListener('click', (e) => { e.preventDefault(); closeBurgerMenu(); openBlockedUsersModal(); });
    const burgerReport = document.getElementById('burgerReport');
    if (burgerReport) burgerReport.addEventListener('click', (e) => { e.preventDefault(); closeBurgerMenu(); openReportModal(); });
    const burgerGuidelines = document.getElementById('burgerGuidelines');
    if (burgerGuidelines) burgerGuidelines.addEventListener('click', (e) => { e.preventDefault(); closeBurgerMenu(); openCommunityGuidelinesModal(); });
    const burgerExit = document.getElementById('burgerExit');
    if (burgerExit) burgerExit.addEventListener('click', (e) => { e.preventDefault(); closeBurgerMenu(); });
    const burgerLogout = document.getElementById('burgerLogout');
    if (burgerLogout) burgerLogout.addEventListener('click', (e) => { e.preventDefault(); closeBurgerMenu(); handleLogout(); });
    
    // Admin Panel
    document.getElementById('adminPanelBtn')?.addEventListener('click', (e) => {
        e.preventDefault();
        const modal = new bootstrap.Modal(document.getElementById('adminPanelModal'));
        modal.show();
        loadAdminData();
    });

    // User search
    const searchInput = document.getElementById('userSearchInput');
    const searchToggle = document.getElementById('navSearchToggle');
    const searchClose = document.getElementById('navSearchClose');
    if (searchInput) {
        const debounced = debounce(handleUserSearch, 250);
        searchInput.addEventListener('input', debounced);
        searchToggle?.addEventListener('click', openCompactSearch);
        searchClose?.addEventListener('click', closeCompactSearch);
        window.addEventListener('resize', () => {
            if (!isCompactNavbar()) closeCompactSearch();
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeCompactSearch();
        });
        document.addEventListener('click', (e) => {
            const wrap = document.getElementById('navSearchWrap');
            if (wrap && !wrap.contains(e.target)) {
                hideUserSearchResults();
                if (isCompactNavbar()) {
                    const toggle = document.getElementById('navSearchToggle');
                    if (!toggle || !toggle.contains(e.target)) closeCompactSearch();
                }
            }
        });
    }

    // Edit post modals
    document.getElementById('saveEditPostBtn')?.addEventListener('click', saveEditedPost);
    document.getElementById('editPostMediaInput')?.addEventListener('change', handleEditPostMediaSelect);
    document.getElementById('editPostMediaManagerAddBtn')?.addEventListener('click', () => {
        document.getElementById('editPostMediaInput')?.click();
    });
    document.getElementById('editPostModal')?.addEventListener('hidden.bs.modal', resetEditPostState);
    document.getElementById('editPostMediaManagerModal')?.addEventListener('shown.bs.modal', renderEditPostMediaManager);
    document.getElementById('mediaViewerPrevBtn')?.addEventListener('click', showPrevMediaInViewer);
    document.getElementById('mediaViewerNextBtn')?.addEventListener('click', showNextMediaInViewer);

    // Media viewer cleanup
    const mediaViewerModal = document.getElementById('mediaViewerModal');
    if (mediaViewerModal) {
        mediaViewerModal.addEventListener('hidden.bs.modal', () => {
            const imageEl = document.getElementById('mediaViewerImageEl');
            const videoEl = document.getElementById('mediaViewerVideoEl');
            if (videoEl) {
                videoEl.pause();
                videoEl.removeAttribute('src');
                videoEl.classList.add('d-none');
            }
            if (imageEl) {
                imageEl.removeAttribute('src');
                imageEl.classList.add('d-none');
            }
            mediaViewerState = { items: [], index: 0 };
            updateMediaViewerControls();
        });
    }
    document.addEventListener('keydown', (e) => {
        const isViewerOpen = document.getElementById('mediaViewerModal')?.classList.contains('show');
        if (!isViewerOpen) return;
        if (e.key === 'ArrowLeft') showPrevMediaInViewer();
        if (e.key === 'ArrowRight') showNextMediaInViewer();
    });
    
    // MyDay / Stories
    document.getElementById('addStoryBtn')?.addEventListener('click', () => document.getElementById('storyMediaInput')?.click());
    document.getElementById('storyMediaInput')?.addEventListener('change', handleStoryUpload);
    document.getElementById('burgerNotes')?.addEventListener('click', function(e) { e.preventDefault(); closeBurgerMenu(); openNotesModal(); });
    document.getElementById('burgerArchiveStories')?.addEventListener('click', function(e) { e.preventDefault(); closeBurgerMenu(); openArchiveStoriesModal(); });
    document.getElementById('profileNoteEditBtn')?.addEventListener('click', function() { openCreateNoteModal(); });
    document.body.addEventListener('click', function(e) {
        if (e.target.closest('#createNoteBtn')) { e.preventDefault(); e.stopPropagation(); openCreateNoteModal(); }
    });
    document.getElementById('noteFormSaveBtn')?.addEventListener('click', saveNoteFromForm);
    document.getElementById('noteFormContent')?.addEventListener('input', function() {
        const c = document.getElementById('noteCharCount');
        if (c) c.textContent = (this.value || '').length;
    });
    document.getElementById('notesModal')?.addEventListener('shown.bs.modal', function() {
        document.getElementById('notesListContainer').innerHTML = '<div class="text-center py-3"><span class="spinner-border spinner-border-sm"></span> Loading...</div>';
        loadNotes();
    });

    // Allow only one video to play at a time across the whole app.
    document.addEventListener('play', function (e) {
        const playingVideo = e.target;
        if (!(playingVideo instanceof HTMLVideoElement)) return;
        document.querySelectorAll('video').forEach((videoEl) => {
            if (videoEl !== playingVideo && !videoEl.paused) {
                videoEl.pause();
            }
        });
    }, true);
    
    // Announcement checkbox handler
    const announcementCheckbox = document.getElementById('isAnnouncement');
    if (announcementCheckbox) {
        const label = announcementCheckbox.closest('label');
        if (label) {
            label.addEventListener('click', function(e) {
                if (e.target !== announcementCheckbox && e.target.tagName !== 'I' && e.target.tagName !== 'SPAN') {
                    e.preventDefault();
                    announcementCheckbox.checked = !announcementCheckbox.checked;
                    // Update visual state
                    if (announcementCheckbox.checked) {
                        label.style.background = '#0095f6';
                        label.style.color = '#ffffff';
                    } else {
                        label.style.background = 'transparent';
                        label.style.color = '#262626';
                    }
                }
            });
            // Initialize visual state
            if (announcementCheckbox.checked) {
                label.style.background = '#0095f6';
                label.style.color = '#ffffff';
            }
        }
        announcementCheckbox.addEventListener('change', function() {
            const label = this.closest('label');
            if (label) {
                if (this.checked) {
                    label.style.background = '#0095f6';
                    label.style.color = '#ffffff';
                } else {
                    label.style.background = 'transparent';
                    label.style.color = '#262626';
                }
            }
        });
    }
}

function debounce(fn, delay) {
    let t;
    return (...args) => {
        clearTimeout(t);
        t = setTimeout(() => fn(...args), delay);
    };
}

function isCompactNavbar() {
    return window.matchMedia('(max-width: 991.98px)').matches;
}

function openCompactSearch() {
    if (!isCompactNavbar()) return;
    const brand = document.getElementById('navBrand');
    const links = document.getElementById('navLinks');
    const burger = document.getElementById('navBurger');
    const searchToggle = document.getElementById('navSearchToggle');
    const searchWrap = document.getElementById('navSearchWrap');
    const input = document.getElementById('userSearchInput');

    brand?.classList.add('d-none');
    links?.classList.add('d-none');
    burger?.classList.add('d-none');
    searchToggle?.classList.add('d-none');
    if (searchWrap) {
        searchWrap.classList.remove('d-none', 'd-lg-flex');
        searchWrap.classList.add('d-flex', 'w-100', 'mx-0');
    }
    if (input) {
        input.focus();
        input.select();
    }
}

function closeCompactSearch() {
    const brand = document.getElementById('navBrand');
    const links = document.getElementById('navLinks');
    const burger = document.getElementById('navBurger');
    const searchToggle = document.getElementById('navSearchToggle');
    const searchWrap = document.getElementById('navSearchWrap');

    brand?.classList.remove('d-none');
    links?.classList.remove('d-none');
    burger?.classList.remove('d-none');
    searchToggle?.classList.remove('d-none');
    if (searchWrap) {
        searchWrap.classList.remove('d-flex', 'w-100', 'mx-0');
        searchWrap.classList.add('d-none', 'd-lg-flex');
    }
    hideUserSearchResults();
}

function hideUserSearchResults() {
    const box = document.getElementById('userSearchResults');
    if (box) {
        box.classList.remove('active');
        box.innerHTML = '';
    }
}

function renderUserSearchResults(users) {
    const box = document.getElementById('userSearchResults');
    if (!box) return;

    if (!users || users.length === 0) {
        box.innerHTML = `<div class="nav-search-empty">No users found</div>`;
        box.classList.add('active');
        return;
    }

    box.innerHTML = users.map(u => `
        <div class="nav-search-item">
            <div class="nav-search-user" onclick="openUserProfile(${u.id})">
                <img src="${u.avatar}" class="nav-search-avatar" onerror="this.src='https://via.placeholder.com/36'">
                <div class="nav-search-meta">
                    <div class="nav-search-name">${escapeHtml(u.name)}</div>
                    <div class="nav-search-username">@${escapeHtml(u.username || '')}</div>
                </div>
            </div>
            ${u.id !== currentUser.id ? `
                <button class="nav-follow-btn ${u.isFollowed ? 'following' : ''}" onclick="toggleFollowFromSearch(event, ${u.id})">
                    ${u.isFollowed ? 'Following' : 'Follow'}
                </button>
            ` : `<span class="nav-search-me">You</span>`}
        </div>
    `).join('');
    box.classList.add('active');
}

function handleUserSearch() {
    const q = (document.getElementById('userSearchInput')?.value || '').trim();
    if (q.length < 2) {
        hideUserSearchResults();
        return;
    }

    fetch(`api/search_users.php?q=${encodeURIComponent(q)}&viewer_id=${encodeURIComponent(currentUser.id)}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                renderUserSearchResults(data.users);
            } else {
                hideUserSearchResults();
            }
        })
        .catch(() => hideUserSearchResults());
}

function toggleFollowFromSearch(e, userId) {
    e.stopPropagation();
    fetch('api/toggle_follow.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ follower_id: currentUser.id, followed_id: userId })
    })
        .then(r => r.json())
        .then(() => {
            // Refresh results (so button state updates)
            handleUserSearch();
            // Refresh my sidebar stats (followers/following) if needed
            loadMyProfileStats().then(() => updateOnboardingState());
        })
        .catch(() => {});
}

function openUserProfile(userId) {
    hideUserSearchResults();
    fetch(`api/get_profile.php?user_id=${encodeURIComponent(userId)}&viewer_id=${encodeURIComponent(currentUser.id)}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.profile) return;
            showProfileChoice(data.profile);
        })
        .catch(() => {});
}

function openUserProfileToPost(userId, postId) {
    hideUserSearchResults();
    fetch(`api/get_profile.php?user_id=${encodeURIComponent(userId)}&viewer_id=${encodeURIComponent(currentUser.id)}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.profile) return;
            focusPostId = postId ? Number(postId) : null;
            setActiveNav('profile');
            visitProfile(data.profile);
        })
        .catch(() => {});
}

function cleanupModalArtifacts() {
    document.querySelectorAll('.modal-backdrop').forEach((el) => el.remove());
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('padding-right');
}

function showProfileChoice(profile) {
    if (!profile) return;
    window._profileChoiceTarget = profile;
    const modalEl = document.getElementById('profileChoiceModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const viewStoryBtn = document.getElementById('profileChoiceViewStory');
    const viewProfileBtn = document.getElementById('profileChoiceViewProfile');
    viewStoryBtn.style.display = (profile.hasStory === true) ? 'block' : 'none';
    viewStoryBtn.onclick = () => {
        let handled = false;
        const onHidden = () => {
            if (handled) return;
            handled = true;
            cleanupModalArtifacts();
            if (profile.hasStory) {
                loadStories().then(() => {
                    const u = storyUsers.find(x => x.user_id === profile.id);
                    if (u) openStoryViewer(profile.id);
                });
            }
        };
        modalEl.addEventListener('hidden.bs.modal', onHidden, { once: true });
        modal.hide();
        setTimeout(onHidden, 300);
    };
    viewProfileBtn.onclick = () => {
        let handled = false;
        const onHidden = () => {
            if (handled) return;
            handled = true;
            cleanupModalArtifacts();
            setActiveNav('profile');
            visitProfile(profile);
        };
        modalEl.addEventListener('hidden.bs.modal', onHidden, { once: true });
        modal.hide();
        setTimeout(onHidden, 300);
    };
    modal.show();
}

function loadMyProfileStats() {
    const reqSeq = ++profileStatsRequestSeq;
    const profileUrl = `api/get_profile.php?user_id=${encodeURIComponent(currentUser.id)}&viewer_id=${encodeURIComponent(currentUser.id)}&_ts=${Date.now()}`;
    return fetch(profileUrl, { cache: 'no-store' })
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            if (reqSeq !== profileStatsRequestSeq) return; // ignore stale response
            profileStats = {
                postCount: data.profile.postCount ?? 0,
                followerCount: data.profile.followerCount ?? 0,
                followingCount: data.profile.followingCount ?? 0,
            };
            isNewUserGuideDismissedForAccount = !!data.profile.new_user_guide_dismissed;
            const isAdmin = isAdminRole();
            isNewUser = !isAdmin && profileStats.followingCount === 0 && !isNewUserGuideDismissedForAccount;
            document.getElementById('postCount').textContent = profileStats.postCount;
            document.getElementById('followerCount').textContent = profileStats.followerCount;
            document.getElementById('followingCount').textContent = profileStats.followingCount;
            updateOnboardingState();
            // Update sidebar with full profile (latestNote, hasStory) when viewing self
            if (!viewedProfile || Number(viewedProfile.id) === Number(currentUser.id)) {
                viewedProfile = { ...(viewedProfile || currentUser), ...data.profile };
                updateProfileSidebar(viewedProfile);
            }
        })
        .catch(() => {});
}

function applyLocalPostCountDelta(delta) {
    if (!Number.isFinite(delta)) return;
    const next = Math.max(0, (Number(profileStats.postCount) || 0) + delta);
    profileStats.postCount = next;
    const postCountEl = document.getElementById('postCount');
    if (postCountEl) postCountEl.textContent = next;
    if (viewedProfile && Number(viewedProfile.id) === Number(currentUser.id)) {
        viewedProfile.postCount = next;
        updateProfileSidebar(viewedProfile);
    }
}

function updateViewHeader() {
    const h = document.getElementById('viewHeader');
    if (!h) return;
    if (currentView === 'profile' && viewedProfile) {
        const isMe = Number(viewedProfile.id) === Number(currentUser.id);
        const displayName = escapeHtml(viewedProfile.name || viewedProfile.username || 'Profile');
        const bio = escapeHtml((viewedProfile.bio || '').trim());
        const avatar = viewedProfile.avatar || 'assets/images/default-avatar.png';
        const cover = (viewedProfile.cover_photo && String(viewedProfile.cover_photo).trim())
            ? String(viewedProfile.cover_photo).trim()
            : '';
        const safeCover = cover.replace(/'/g, "\\'");
        const friendLabel = viewedProfile.isFollowed ? 'Unfollow' : 'Follow';

        h.innerHTML = `
            <div class="view-header-card profile-view-header">
                <div class="profile-view-cover ${cover ? '' : 'no-cover'}" ${cover ? `style="background-image:url('${safeCover}');"` : ''}></div>
                <div class="profile-view-body">
                    <img src="${avatar}" alt="${displayName}" class="profile-view-avatar avatar-zoom" onerror="this.onerror=null;this.src='assets/images/default-avatar.png'">
                    <div class="profile-view-meta">
                        <div class="view-title">${displayName}</div>
                        ${bio ? `<div class="view-subtitle profile-view-bio">${bio}</div>` : ''}
                    </div>
                    <div class="d-flex flex-wrap gap-2 ms-auto profile-view-actions">
                        ${isMe ? `
                            <button class="btn btn-sm btn-primary" onclick="document.getElementById('storyMediaInput')?.click()">
                                <i class="fas fa-plus-circle"></i> Add Story
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="openProfileEditor()">
                                <i class="fas fa-pen"></i> Edit Profile
                            </button>
                        ` : `
                            <button class="btn btn-sm ${viewedProfile.isFollowed ? 'btn-secondary' : 'profile-follow-btn'}" onclick="toggleFollowProfile(${viewedProfile.id})">
                                <i class="fas ${viewedProfile.isFollowed ? 'fa-user-minus' : 'fa-user-plus'}"></i> ${friendLabel}
                            </button>
                        `}
                    </div>
                </div>
            </div>
        `;
    } else {
        h.innerHTML = '';
    }
}

function updateStoriesVisibility() {
    const storiesRow = document.getElementById('storiesRow');
    const createPostSection = document.getElementById('createPostSection');
    const showHomeOnly = currentView === 'home';
    if (storiesRow) {
        storiesRow.style.display = showHomeOnly ? '' : 'none';
    }
    if (createPostSection) {
        createPostSection.style.display = showHomeOnly ? '' : 'none';
    }
}

function navigateTo(page) {
    setActiveNav(page);

    if (page === 'home') {
        currentView = 'home';
        currentFilterUserId = null
        updateStoriesVisibility();
        viewedProfile = {...currentUser};
        updateProfileSidebar(viewedProfile);
        updateViewHeader();
        loadPosts();
        loadMyProfileStats();
        
        window.scrollTo({ top: 0, behavior: 'smooth' });
        return;
    }

    if (page === 'profile') {
        fetch(`api/get_profile.php?user_id=${currentUser.id}&viewer_id=${currentUser.id}`)
            .then(r => r.json())
            .then(data => {
                if (data.success && data.profile) {
                    visitProfile(data.profile);
                } else {
                    visitProfile(currentUser);
                }
            })
            .catch(() => visitProfile(currentUser));
        return;
    }

}

function handleMediaPreview(e) {
    const incoming = Array.from(e.target.files || []);
    if (!incoming.length) return;

    for (const file of incoming) {
        if (!validateFile(file, { allowVideo: true, maxSizeMB: 30 })) {
            e.target.value = '';
            return;
        }
    }

    const nextFiles = [...selectedMediaFiles];
    incoming.forEach((file) => {
        const exists = selectedMediaFiles.some(
            (f) => f.name === file.name && f.size === file.size && f.lastModified === file.lastModified
        );
        if (!exists) {
            nextFiles.push(file);
        }
    });

    if (!validateTotalUploadSize(nextFiles)) {
        e.target.value = '';
        return;
    }

    selectedMediaFiles = nextFiles;

    e.target.value = '';
    renderCreatePostMediaPreview();
}

function renderCreatePostMediaPreview() {
    const preview = document.getElementById('mediaPreview');
    if (!preview) return;

    if (!selectedMediaFiles.length) {
        preview.style.display = 'none';
        preview.innerHTML = '';
        updateCreatePostDiscardVisibility();
        return;
    }

    preview.style.display = 'block';
    preview.innerHTML = `
        <div class="create-draft-media-grid">
            ${selectedMediaFiles.map((file, index) => {
                const url = URL.createObjectURL(file);
                const isVideo = file.type.startsWith('video/');
                return `
                    <div class="create-draft-media-item">
                        <button type="button" class="btn btn-sm btn-danger create-draft-remove-btn" onclick="removeSelectedCreateMedia(${index})" title="Remove media">
                            <i class="fas fa-times"></i>
                        </button>
                        ${isVideo
                            ? `<video src="${url}" controls playsinline onloadeddata="URL.revokeObjectURL('${url}')"></video>`
                            : `<img src="${url}" alt="Preview" onload="URL.revokeObjectURL('${url}')">`
                        }
                    </div>
                `;
            }).join('')}
            <button type="button" class="btn btn-outline-primary create-draft-add-more" onclick="document.getElementById('postMedia')?.click()">
                <i class="fas fa-plus"></i> Add Media
            </button>
        </div>
    `;
    updateCreatePostDiscardVisibility();
}

function removeSelectedCreateMedia(index) {
    if (index < 0 || index >= selectedMediaFiles.length) return;
    selectedMediaFiles.splice(index, 1);
    renderCreatePostMediaPreview();
}

function discardCreatePostDraft() {
    document.getElementById('postContent').value = '';
    document.getElementById('postMedia').value = '';
    selectedMediaFiles = [];
    renderCreatePostMediaPreview();
    updateCreatePostDiscardVisibility();
}

function hasCreatePostDraftChanges() {
    const content = (document.getElementById('postContent')?.value || '').trim();
    return content.length > 0 || selectedMediaFiles.length > 0;
}

function updateCreatePostDiscardVisibility() {
    const wrap = document.getElementById('discardPostDraftWrap');
    if (!wrap) return;
    wrap.classList.toggle('d-none', !hasCreatePostDraftChanges());
}

function validateFile(file, options = {}) {
    const allowVideo = !!options.allowVideo;
    const maxSizeMB = Number(options.maxSizeMB || 5);
    const maxSize = maxSizeMB * 1024 * 1024;

    if ((file.size || 0) > maxSize) {
        Swal.fire({
            icon: 'error',
            title: 'File Too Large',
            text: `Maximum file size is ${maxSizeMB}MB`,
            confirmButtonColor: '#6366f1'
        });
        document.getElementById('postMedia').value = '';
        return false;
    }

    // Check file format
    const allowedTypes = allowVideo
        ? ['image/png', 'image/jpeg', 'image/jpg', 'video/mp4']
        : ['image/png', 'image/jpeg', 'image/jpg'];
    if (!allowedTypes.includes(file.type)) {
        Swal.fire({
            icon: 'error',
            title: 'Invalid File Format',
            text: allowVideo ? 'Only PNG, JPG, and MP4 files are allowed' : 'Only PNG and JPG images are allowed',
            confirmButtonColor: '#6366f1'
        });
        document.getElementById('postMedia').value = '';
        return false;
    }
    
    return true;
}

function bytesToMb(bytes) {
    return Number(bytes || 0) / (1024 * 1024);
}

function getTotalFilesBytes(files) {
    return (files || []).reduce((sum, f) => sum + Number(f?.size || 0), 0);
}

function validateTotalUploadSize(files, maxTotalMB = MAX_TOTAL_UPLOAD_MB) {
    const totalBytes = getTotalFilesBytes(files);
    const totalMB = bytesToMb(totalBytes);
    if (totalMB > maxTotalMB) {
        Swal.fire({
            icon: 'error',
            title: 'Total Upload Too Large',
            text: `Total selected media is ${totalMB.toFixed(1)}MB. Maximum allowed is ${maxTotalMB}MB.`,
            confirmButtonColor: '#6366f1'
        });
        return false;
    }
    return true;
}

function createPost() {
    const content = document.getElementById('postContent').value.trim();
    const mediaFiles = selectedMediaFiles;
    const isAnnouncement = document.getElementById('isAnnouncement')?.checked || false;
    
    if (!content && mediaFiles.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Empty Post',
            text: 'Please enter some content or add a photo/video',
            confirmButtonColor: '#6366f1'
        });
        return;
    }
    
    // Show loading
    const btn = document.getElementById('createPostBtn');
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<span class="loading"></span> Posting...';
    btn.disabled = true;
    
    // Prepare form data
    const formData = new FormData();
    formData.append('content', content);
    if (mediaFiles.length > 0) {
        mediaFiles.forEach((f) => formData.append('media[]', f));
    }
    formData.append('user_id', currentUser.id);
    if (isAnnouncement) {
        formData.append('post_type', 'announcement');
    } else {
        const pr = document.getElementById('postPrivacy');
        formData.append('privacy', pr ? pr.value : 'public');
    }
    
    // Send to backend
    fetch('api/create_post.php', {
        method: 'POST',
        body: formData
    })
    .then(async (response) => {
        const raw = await response.text();
        let data = null;
        try {
            data = JSON.parse(raw);
        } catch (e) {
            throw new Error(raw || `Request failed with status ${response.status}`);
        }
        if (!response.ok) {
            throw new Error(data?.message || `Request failed with status ${response.status}`);
        }
        return data;
    })
    .then(data => {
        if (data.success) {
            const isFacAnn = isAnnouncement && (currentUser.accountType === 'faculty');
            Swal.fire({
                icon: 'success',
                title: 'Posted!',
                text: isFacAnn ? 'Your announcement was submitted. It will appear after admin approval.' : 'Your post has been shared',
                showConfirmButton: false,
                timer: isFacAnn ? 2500 : 1500
            });
            
            // Reset form
            discardCreatePostDraft();
            document.getElementById('isAnnouncement').checked = false;
            const pr = document.getElementById('privacyRow');
            if (pr) pr.style.display = 'flex';
            
            // Reload feed and refresh true profile counters from backend.
            loadPosts();
            loadMyProfileStats();
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message || 'Failed to create post',
                confirmButtonColor: '#6366f1'
            });
        }
        btn.innerHTML = originalHTML;
        btn.disabled = false;
    })
    .catch(error => {
        console.error('Error:', error);
        const msg = (error && error.message ? String(error.message) : '').replace(/<[^>]*>/g, ' ').trim();
        if (msg && !msg.toLowerCase().includes('unexpected token')) {
            Swal.fire({
                icon: 'error',
                title: 'Upload Failed',
                text: msg.slice(0, 300),
                confirmButtonColor: '#6366f1'
            });
            btn.innerHTML = originalHTML;
            btn.disabled = false;
            return;
        }
        // Demo mode - create post locally
        const newPost = {
            id: Date.now(),
            user_id: currentUser.id,
            username: currentUser.username,
            name: currentUser.name || currentUser.username,
            avatar: currentUser.avatar || 'assets/images/default-avatar.png',
            content: content,
            media: mediaFiles.length ? mediaFiles.map(f => URL.createObjectURL(f)) : null,
            likes: 0,
            comments: [],
            shares: 0,
            timestamp: new Date().toISOString(),
            isLiked: false
        };
        
        posts.unshift(newPost);
        renderPosts();
        loadMyProfileStats();
        
        Swal.fire({
            icon: 'success',
            title: 'Posted!',
            text: 'Your post has been shared',
            showConfirmButton: false,
            timer: 1500
        });
        
        // Reset form
        discardCreatePostDraft();
        
        btn.innerHTML = originalHTML;
        btn.disabled = false;
    });
}

function loadPosts() {
    // If viewing someone's profile, include profile_user_id parameter
    const profileParam = currentFilterUserId && currentFilterUserId !== currentUser.id 
        ? `&profile_user_id=${currentFilterUserId}` 
        : '';
    const annParam = announcementsOnly ? '&announcements_only=1' : '';
    fetch(`api/get_posts.php?user_id=${currentUser.id}${profileParam}${annParam}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                posts = data.posts;
                updateOnboardingState();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            // Demo posts
            
            updateOnboardingState();
        });
}

function renderPosts() {
    const container = document.getElementById('timelinePosts');
    container.innerHTML = '';
    
    const filtered = currentFilterUserId
        ? posts.filter(p => p.user_id === currentFilterUserId)
        : posts;

    if (filtered.length === 0) {
        container.innerHTML = '<div class="text-center py-5"><p class="text-muted">No posts yet. Be the first to post!</p></div>';
        return;
    }
    
    filtered.forEach(post => {
        const postElement = createPostElement(post);
        container.appendChild(postElement);
    });
    
    // If a post is targeted (e.g., from notification), scroll and highlight it
    if (focusPostId) {
        setTimeout(() => {
            const el = document.querySelector(`[data-post-id="${focusPostId}"]`);
            if (el) {
                el.classList.add('post-highlight');
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                setTimeout(() => el.classList.remove('post-highlight'), 2000);
            }
            focusPostId = null;
        }, 150);
    }
}

function createPostElement(post) {
    const div = document.createElement('div');
    const isAnnouncement = post.post_type === 'announcement';
    const isAdminPost = post.account_type === 'admin';
    const canStaffModerateThisPost = !isAdminPost && !isAnnouncement;
    const showModeratorMenu = isAdminRole() || (isStaffRole() && canStaffModerateThisPost);
    div.className = 'post-card' + (isAnnouncement ? ' announcement' : '');
    div.dataset.postId = post.id;
    
    const timeAgo = getTimeAgo(post.timestamp);
    const isOwner = post.user_id === currentUser.id;
    const mediaHtml = renderPostMedia(post);
    const isSharedPost = !!(post.reference_post && post.reference);
    const sharedRefHtml = isSharedPost ? renderSharedReferencePost(post.reference) : '';
    const sharerContentHtml = post.content
        ? `<div class="post-content">${escapeHtml(post.content)}</div>`
        : '';

    div.innerHTML = `
        <div class="post-header">
            <img src="${post.avatar}" alt="${post.name}" class="post-avatar clickable-profile" onclick="openUserProfile(${post.user_id})" onerror="this.src='imagesrc/default-avatar.png'">
            <div class="post-user-info">
                <div class="post-user-name clickable-profile" onclick="openUserProfile(${post.user_id})">
                    ${escapeHtml(post.name)}
                    ${isAnnouncement ? '<span class="announcement-badge"><i class="fas fa-bullhorn"></i> Announcement</span>' : ''}
                    ${post.account_type === 'faculty' ? '<span class="badge-faculty">Faculty</span>' : ''}
                    ${post.account_type === 'admin' ? '<span class="badge-admin">Admin</span>' : ''}
                </div>
                <div class="post-time">
                    ${timeAgo}
                    ${post.privacy ? `<span class="privacy-badge ms-1" title="${privacyLabel(post.privacy)}"><i class="fas ${privacyIcon(post.privacy)}"></i></span>` : ''}
                    ${isSharedPost ? '<span class="ms-2 text-muted">shared a post</span>' : ''}
                </div>
            </div>
            ${isOwner ? `
                <div class="post-actions-menu">
                    <button class="post-menu-btn" onclick="showPostMenu(${post.id})">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                </div>
            ` : (showModeratorMenu ? `
                <div class="post-actions-menu">
                    <button class="post-menu-btn admin-action-btn" onclick="showAdminPostMenu(${post.id}, ${post.user_id}, '${post.account_type || 'student'}', '${post.post_type || 'post'}')" title="Moderator Actions">
                        <i class="fas fa-shield-alt"></i>
                    </button>
                </div>
            ` : '')}
        </div>
        ${sharerContentHtml}
        ${isSharedPost ? sharedRefHtml : mediaHtml}
        <div class="post-interactions">
            <button class="interaction-btn ${post.isLiked ? 'liked' : ''}" onclick="toggleLike(${post.id})">
                <i class="fas fa-heart"></i>
                <span>${post.likes}</span>
            </button>
            <button class="interaction-btn" onclick="toggleComments(${post.id})">
                <i class="fas fa-comment"></i>
                <span>${post.comments.length}</span>
            </button>
            <button class="interaction-btn" onclick="sharePost(${post.id})">
                <i class="fas fa-share"></i>
                <span>${post.shares}</span>
            </button>
        </div>
        <div class="comment-section" id="comments-${post.id}">
            <div class="comment-input-group">
                <input type="text" class="comment-input" id="comment-input-${post.id}" placeholder="Write a comment...">
                <button class="comment-btn" onclick="addComment(${post.id})">Post</button>
            </div>
            <div class="comments-list" id="comments-list-${post.id}">
                ${renderComments(post.comments)}
            </div>
        </div>
    `;
    
    return div;
}

function renderSharedReferencePost(reference) {
    if (!reference) return '';
    const refMediaHtml = renderPostMedia(reference);
    const refTimeAgo = getTimeAgo(reference.timestamp);
    return `
        <div class="shared-reference-card">
            <div class="post-header shared-reference-header">
                <img src="${reference.avatar}" alt="${reference.name}" class="post-avatar clickable-profile" onclick="openUserProfileToPost(${reference.user_id}, ${reference.id})" onerror="this.src='imagesrc/default-avatar.png'">
                <div class="post-user-info">
                    <div class="post-user-name clickable-profile" onclick="openUserProfileToPost(${reference.user_id}, ${reference.id})">
                        ${escapeHtml(reference.name)}
                    </div>
                    <div class="post-time">${refTimeAgo}</div>
                </div>
            </div>
            ${reference.content ? `<div class="post-content">${escapeHtml(reference.content)}</div>` : ''}
            ${refMediaHtml}
        </div>
    `;
}

function visitProfile(profile) {
    if (!profile) return;
    viewedProfile = profile;
    currentView = 'profile';
    currentFilterUserId = profile.id;
    updateStoriesVisibility();
    updateViewHeader();
    updateProfileSidebar(profile);
    loadPosts(); // Reload posts with profile filter
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function updateProfileSidebar(profile) {
    const isMe = Number(profile.id) === Number(currentUser.id);
    
    // Update sidebar profile info
    document.getElementById('profileName').textContent = profile.name || profile.username;
    const profileUsernameEl = document.getElementById('profileUsername');
    if (profileUsernameEl) {
        profileUsernameEl.textContent = '';
        profileUsernameEl.style.display = 'none';
    }
    
    // Update avatar - make clickable for View Story/Profile choice
    const sidebarAvatar = document.getElementById('sidebarProfileAvatar');
    if (profile.avatar) {
        
        if (sidebarAvatar) {
            sidebarAvatar.onerror = function () {
                this.onerror = null;
                this.src = 'assets/images/default-avatar.png';
            };
            sidebarAvatar.src = profile.avatar;
        }
    }
    
    if (sidebarAvatar) {
        sidebarAvatar.onclick = () => showProfileChoice(profile);
        sidebarAvatar.style.cursor = 'pointer';
    }
    const profileNameEl = document.getElementById('profileName');
    if (profileNameEl) {
        profileNameEl.onclick = () => showProfileChoice(profile);
        profileNameEl.style.cursor = 'pointer';
    }
    
    // Update note near profile pic
    const noteBadge = document.getElementById('profileNoteBadge');
    const noteText = document.getElementById('profileNoteText');
    const noteEditBtn = document.getElementById('profileNoteEditBtn');
    const noteDeleteBtn = document.getElementById('profileNoteDeleteBtn');
    const noteLikeBtn = document.getElementById('profileNoteLikeBtn');
    if (noteBadge && noteText) {
        if (profile.latestNote && profile.latestNote.content) {
            noteBadge.style.display = 'flex';
            noteText.textContent = profile.latestNote.content;
            noteText.onclick = isMe ? () => openEditNoteModal(profile.latestNote.id) : null;
            if (noteEditBtn) {
                noteEditBtn.style.display = isMe ? 'inline-flex' : 'none';
                noteEditBtn.onclick = isMe ? (e) => { e.stopPropagation(); openEditNoteModal(profile.latestNote.id); } : null;
            }
            if (noteDeleteBtn) {
                noteDeleteBtn.style.display = isMe ? 'inline-flex' : 'none';
                noteDeleteBtn.onclick = isMe ? (e) => { e.stopPropagation(); deleteNoteFromProfile(profile.latestNote.id); } : null;
            }
            if (noteLikeBtn) {
                noteLikeBtn.style.display = isMe ? 'none' : 'inline-flex';
                noteLikeBtn.onclick = (e) => { e.stopPropagation(); likeNoteFromProfile(profile.latestNote); };
                noteLikeBtn.innerHTML = `<i class="far fa-heart ${profile.latestNote.liked ? 'fas text-danger' : ''}"></i> <span id="profileNoteLikeCount">${profile.latestNote.like_count || 0}</span>`;
            }
        } else {
            noteBadge.style.display = isMe ? 'flex' : 'none';
            noteText.textContent = isMe ? 'Type your note here' : '';
            noteText.onclick = isMe ? () => openCreateNoteModal() : null;
            if (noteEditBtn) {
                noteEditBtn.style.display = isMe ? 'inline-flex' : 'none';
                noteEditBtn.onclick = isMe ? (e) => { e.stopPropagation(); openCreateNoteModal(); } : null;
            }
            if (noteDeleteBtn) noteDeleteBtn.style.display = 'none';
            if (noteLikeBtn) noteLikeBtn.style.display = 'none';
        }
    }
    
    // Update cover photo
    if (profile.cover_photo) {
        setCoverPhoto(profile.cover_photo);
    }
    
    // Update stats
    document.getElementById('postCount').textContent = profile.postCount ?? 0;
    document.getElementById('followerCount').textContent = profile.followerCount ?? 0;
    document.getElementById('followingCount').textContent = profile.followingCount ?? 0;
    
    // Hide edit buttons if viewing someone else's profile
    const changeAvatarBtn = document.getElementById('changeAvatarBtn');
    const editProfileBtn = document.getElementById('editProfileBtn');
    if (changeAvatarBtn) changeAvatarBtn.style.display = isMe ? 'block' : 'none';
    if (editProfileBtn) editProfileBtn.style.display = isMe ? 'block' : 'none';
}

function deleteNoteFromProfile(noteId) {
    Swal.fire({ title: 'Delete note?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444' }).then((r) => {
        if (r.isConfirmed) {
            fetch('api/delete_note.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ note_id: noteId, user_id: currentUser.id })
            }).then(res => res.json()).then(data => {
                if (data.success) {
                    loadMyProfileStats();
                    loadNotes();
                    Swal.fire('Deleted', 'Note deleted.', 'success');
                }
            });
        }
    });
}

function likeNoteFromProfile(note) {
    if (!note || note.id === undefined) return;
    fetch('api/like_note.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ note_id: note.id, user_id: currentUser.id })
    })
        .then(r => r.json())
        .then(data => {
            if (data.success && viewedProfile && viewedProfile.latestNote && viewedProfile.latestNote.id === note.id) {
                viewedProfile.latestNote.liked = data.liked;
                viewedProfile.latestNote.like_count = (viewedProfile.latestNote.like_count || 0) + (data.liked ? 1 : -1);
                updateProfileSidebar(viewedProfile);
            }
        })
        .catch(() => {});
}

function toggleFollowProfile(userId) {
    if (!userId || !viewedProfile || viewedProfile.id !== userId) return;
    fetch('api/toggle_follow.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ follower_id: currentUser.id, followed_id: userId })
    })
        .then(r => r.json())
        .then(() => {
            viewedProfile.isFollowed = !viewedProfile.isFollowed;
            updateOnboardingState();
            updateViewHeader();
        })
        .catch(() => {});
}

function setActiveNav(page) {
    document.querySelectorAll('.navbar-nav-custom .nav-link').forEach(link => {
        link.classList.toggle('active', link.getAttribute('data-page') === page);
    });
}

function renderNewUserGuide() {
    if (hasShownNewUserGuidePopup || Swal.isVisible() || isNewUserGuideDismissedForAccount) return;
    hasShownNewUserGuidePopup = true;

    Swal.fire({
        title: 'Welcome to Campus Connect!',
        html: `
            <p class="text-muted">Para makita ang feed, mag-follow muna ng ibang users.</p>
            <ul class="text-start">
                <li>Gamitin ang search bar sa taas para hanapin ang classmates o orgs.</li>
                <li>Kapag may fina-follow ka na, lalabas na ang mga posts sa feed.</li>
                <li>Maaari ka ring gumawa ng sarili mong unang post.</li>
            </ul>
            <div class="d-flex gap-2 justify-content-center mt-3">
                <button id="guideFindPeopleBtn" class="btn btn-primary btn-sm">Find people</button>
                <button id="guideViewProfileBtn" class="btn btn-outline-secondary btn-sm">View my profile</button>
                <button id="guideCancelBtn" class="btn btn-secondary btn-sm">Cancel</button>
            </div>
        `,
        showConfirmButton: false,
        showCloseButton: true,
        allowOutsideClick: true,
        didOpen: () => {
            const findBtn = document.getElementById('guideFindPeopleBtn');
            const profileBtn = document.getElementById('guideViewProfileBtn');
            const cancelBtn = document.getElementById('guideCancelBtn');
            if (findBtn) {
                findBtn.addEventListener('click', () => {
                    dismissNewUserGuideForever();
                    Swal.close();
                    focusUserSearch();
                });
            }
            if (profileBtn) {
                profileBtn.addEventListener('click', () => {
                    dismissNewUserGuideForever();
                    Swal.close();
                    navigateTo('profile');
                });
            }
            if (cancelBtn) {
                cancelBtn.addEventListener('click', () => {
                    dismissNewUserGuideForever();
                    Swal.close();
                });
            }
        }
    }).then((result) => {
        if (result.dismiss) {
            dismissNewUserGuideForever();
        }
    });
}

function focusUserSearch() {
    if (isCompactNavbar()) openCompactSearch();
    const input = document.getElementById('userSearchInput');
    if (input) {
        input.focus();
        input.select();
    }
}

function updateOnboardingState() {
    const isAdmin = isAdminRole();
    renderPosts();
    if (!isAdmin && isNewUser && currentView === 'home') {
        renderNewUserGuide();
    }
}

function isVideoMediaUrl(src) {
    const s = String(src || '').toLowerCase();
    return s.includes('/video/upload/') || s.endsWith('.mp4');
}

function renderPostMedia(post) {
    if (!post || !post.media) return '';

    // Support both single string and array, including legacy object item {url}
    const items = (Array.isArray(post.media) ? post.media : [post.media])
        .map((item) => (item && typeof item === 'object' ? item.url : item))
        .filter(Boolean);
    if (items.length === 0) return '';

    const maxShow = 4;
    const shown = items.slice(0, maxShow);
    const remaining = items.length - shown.length;

    const openPostAttr = (index) => `onclick="openPostMediaViewer(${post.id}, ${index})"`;
    const renderItem = (src, index, thumbClass = 'post-media-thumb') => {
        if (isVideoMediaUrl(src)) {
            return `<video src="${src}" class="${thumbClass}" controls playsinline></video>`;
        }
        return `<img src="${src}" alt="Post media" class="${thumbClass}" ${openPostAttr(index)}>`;
    };

    if (items.length === 1) {
        const src = items[0];
        if (isVideoMediaUrl(src)) {
            return `<video src="${src}" class="post-media" controls playsinline></video>`;
        }
        return `<img src="${src}" alt="Post media" class="post-media" ${openPostAttr(0)}>`;
    }

    return `
        <div class="post-media-grid count-${shown.length}">
            ${shown.map((src, idx) => `
                <div class="post-media-cell ${idx === 0 ? 'first' : ''}">
                    ${renderItem(src, idx)}
                    ${idx === shown.length - 1 && remaining > 0 ? `<div class="post-media-more" ${openPostAttr(idx)}>+${remaining}</div>` : ''}
                </div>
            `).join('')}
        </div>
    `;
}

function updateMediaViewerControls() {
    const prevBtn = document.getElementById('mediaViewerPrevBtn');
    const nextBtn = document.getElementById('mediaViewerNextBtn');
    const counter = document.getElementById('mediaViewerCounter');
    const total = mediaViewerState.items.length;
    const hasMultiple = total > 1;

    if (prevBtn) prevBtn.classList.toggle('d-none', !hasMultiple);
    if (nextBtn) nextBtn.classList.toggle('d-none', !hasMultiple);
    if (counter) {
        counter.classList.toggle('d-none', total === 0);
        if (total > 0) counter.textContent = `${mediaViewerState.index + 1} / ${total}`;
    }
}

function renderMediaViewerAtCurrentIndex() {
    const modalEl = document.getElementById('mediaViewerModal');
    const imageEl = document.getElementById('mediaViewerImageEl');
    const videoEl = document.getElementById('mediaViewerVideoEl');
    const src = mediaViewerState.items[mediaViewerState.index];
    if (!src) return;
    if (!modalEl || !imageEl || !videoEl) return;

    if (isVideoMediaUrl(src)) {
        imageEl.classList.add('d-none');
        imageEl.removeAttribute('src');
        videoEl.classList.remove('d-none');
        videoEl.src = src;
    } else {
        videoEl.pause();
        videoEl.classList.add('d-none');
        videoEl.removeAttribute('src');
        imageEl.classList.remove('d-none');
        imageEl.src = src;
    }

    updateMediaViewerControls();
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}

function openPostMediaViewer(postId, startIndex = 0) {
    const post = posts.find(p => Number(p.id) === Number(postId));
    if (!post || !post.media) return;
    const items = (Array.isArray(post.media) ? post.media : [post.media])
        .map((item) => (item && typeof item === 'object' ? item.url : item))
        .filter(Boolean);
    if (!items.length) return;
    mediaViewerState.items = items;
    mediaViewerState.index = Math.max(0, Math.min(Number(startIndex) || 0, items.length - 1));
    renderMediaViewerAtCurrentIndex();
}

function showPrevMediaInViewer() {
    const total = mediaViewerState.items.length;
    if (total <= 1) return;
    mediaViewerState.index = (mediaViewerState.index - 1 + total) % total;
    renderMediaViewerAtCurrentIndex();
}

function showNextMediaInViewer() {
    const total = mediaViewerState.items.length;
    if (total <= 1) return;
    mediaViewerState.index = (mediaViewerState.index + 1) % total;
    renderMediaViewerAtCurrentIndex();
}

function openMediaViewer(src, isVideo = false) {
    if (!src) return;
    mediaViewerState.items = [src];
    mediaViewerState.index = 0;
    // Keep compatibility with explicit video flag from older callers.
    if (isVideo && !isVideoMediaUrl(src)) {
        const modalEl = document.getElementById('mediaViewerModal');
        const imageEl = document.getElementById('mediaViewerImageEl');
        const videoEl = document.getElementById('mediaViewerVideoEl');
        if (!modalEl || !imageEl || !videoEl) return;
        imageEl.classList.add('d-none');
        imageEl.removeAttribute('src');
        videoEl.classList.remove('d-none');
        videoEl.src = src;
        updateMediaViewerControls();
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
        return;
    }

    if (!validateTotalUploadSize(mediaFiles)) {
        return;
    }
    renderMediaViewerAtCurrentIndex();
}

function openCoverViewer() {
    const cover = document.getElementById('profileCover');
    if (!cover) return;
    const bg = cover.style.backgroundImage || window.getComputedStyle(cover).backgroundImage;
    if (!bg || bg === 'none') return;

    const match = bg.match(/url\(["']?(.*?)["']?\)/);
    const src = match && match[1] ? match[1] : null;
    if (src) {
        openMediaViewer(src);
    }
}

function syncAvatars(src) {
    const fallback = 'assets/images/default-avatar.png';
    const nextSrc = (src && String(src).trim()) ? src : fallback;
    document.querySelectorAll('.profile-avatar, .create-post-avatar, .user-avatar-nav')
        .forEach(img => {
            img.onerror = function () {
                this.onerror = null;
                this.src = fallback;
            };
            if (img.getAttribute('src') !== nextSrc) {
                img.setAttribute('src', nextSrc);
            }
        });
}

function handleAvatarChange(e) {
    const file = e.target.files && e.target.files[0];
    if (!file) return;

    // Validate image
    if (!validateFile(file)) {
        e.target.value = '';
        return;
    }

    // Prefer server upload if possible; fallback to localStorage base64.
    const formData = new FormData();
    formData.append('user_id', currentUser.id);
    formData.append('avatar', file);

    fetch('api/update_avatar.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success && data.avatar) {
                currentUser.avatar = data.avatar;
                localStorage.setItem('user', JSON.stringify(currentUser));
                syncAvatars(currentUser.avatar);
                Swal.fire({ icon: 'success', title: 'Updated!', text: 'Profile photo updated', timer: 1200, showConfirmButton: false });
                return;
            }
            throw new Error(data.message || 'Upload failed');
        })
        .catch(() => {
            const reader = new FileReader();
            reader.onload = () => {
                currentUser.avatar = reader.result;
                localStorage.setItem('user', JSON.stringify(currentUser));
                syncAvatars(currentUser.avatar);
                Swal.fire({ icon: 'success', title: 'Updated!', text: 'Profile photo updated', timer: 1200, showConfirmButton: false });
            };
            reader.readAsDataURL(file);
        })
        .finally(() => {
            e.target.value = '';
        });
}

function openProfileEditor() {
    const html = `
        <div class="profile-edit-form">
            <div class="mb-2">
                <label class="form-label">Name</label>
                <input id="editName" class="form-control" type="text" value="${escapeHtml(currentUser.name || '')}">
            </div>
            <div class="mb-2">
                <label class="form-label">Bio</label>
                <textarea id="editBio" class="form-control" rows="3" placeholder="Tell something about you...">${escapeHtml(currentUser.bio || '')}</textarea>
            </div>
            <div class="mb-2">
                <label class="form-label">Cover Photo</label>
                <input id="editCover" type="file" accept="image/png,image/jpeg,image/jpg" class="form-control">
                <small class="text-muted">PNG/JPG up to 5MB</small>
            </div>
        </div>
    `;

    Swal.fire({
        title: 'Edit Profile',
        html,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Save',
        confirmButtonColor: '#6366f1',
        preConfirm: () => {
            const name = document.getElementById('editName').value.trim();
            const bio = document.getElementById('editBio').value.trim();
            const coverFile = document.getElementById('editCover').files[0];
            return { name, bio, coverFile };
        }
    }).then((result) => {
        if (!result.isConfirmed) return;
        const { name, bio, coverFile } = result.value;

        // Client validation
        if (coverFile && !validateFile(coverFile)) return;

        const formData = new FormData();
        formData.append('user_id', currentUser.id);
        formData.append('name', name);
        formData.append('bio', bio);
        if (coverFile) formData.append('cover', coverFile);

        fetch('api/update_profile.php', {
            method: 'POST',
            body: formData
        })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    Swal.fire('Error', data.message || 'Failed to update profile', 'error');
                    return;
                }
                const u = data.user;
                currentUser.name = u.name;
                currentUser.bio = u.bio;
                currentUser.cover_photo = u.cover_photo;
                localStorage.setItem('user', JSON.stringify(currentUser));
                document.getElementById('profileName').textContent = currentUser.name || currentUser.username;
                setCoverPhoto(currentUser.cover_photo);
                Swal.fire('Saved', 'Profile updated', 'success');
                updateViewHeader(); // update name in subtitle if shown
            })
            .catch(() => Swal.fire('Error', 'Failed to update profile', 'error'));
    });
}

function setCoverPhoto(src) {
    const cover = document.getElementById('profileCover');
    if (!cover) return;
    if (src) {
        cover.style.backgroundImage = `linear-gradient(135deg, rgba(99,102,241,0.6), rgba(139,92,246,0.6)), url('${src}')`;
        cover.style.backgroundSize = 'cover';
        cover.style.backgroundPosition = 'center';
    } else {
        cover.style.backgroundImage = 'linear-gradient(135deg, var(--primary-color), var(--secondary-color))';
    }
}

function renderComments(comments) {
    if (!comments || comments.length === 0) return '';
    
    return comments.map(comment => `
        <div class="comment-item">
            <img src="${comment.avatar || 'https://via.placeholder.com/35'}" alt="${comment.name}" class="comment-avatar" onerror="this.src='https://via.placeholder.com/35'">
            <div class="comment-content">
                <div class="comment-author">${escapeHtml(comment.name)}</div>
                <div class="comment-text">${escapeHtml(comment.text)}</div>
            </div>
        </div>
    `).join('');
}

function toggleLike(postId) {
    const post = posts.find(p => p.id === postId);
    if (!post) return;
    
    fetch('api/like_post.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            post_id: postId,
            user_id: currentUser.id
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            post.isLiked = data.liked;
            post.likes = data.like_count;
            renderPosts();
        }
    })
    .catch(error => {
        // Demo mode
        post.isLiked = !post.isLiked;
        post.likes += post.isLiked ? 1 : -1;
        renderPosts();
    });
}

function toggleComments(postId) {
    const commentSection = document.getElementById(`comments-${postId}`);
    const isOpening = commentSection && commentSection.style.display === 'none';

    // Close all other comment sections first.
    document.querySelectorAll('.comment-section').forEach((section) => {
        if (section.id !== `comments-${postId}`) {
            section.style.display = 'none';
        }
    });

    if (commentSection) {
        commentSection.style.display = isOpening ? 'block' : 'none';
    }
    const input = document.getElementById(`comment-input-${postId}`);
    if (input && commentSection && isOpening) input.focus();
}

function addComment(postId) {
    const input = document.getElementById(`comment-input-${postId}`);
    const text = input.value.trim();
    
    if (!text) return;
    
    const post = posts.find(p => p.id === postId);
    if (!post) return;
    
    const comment = {
        id: Date.now(),
        user_id: currentUser.id,
        name: currentUser.name || currentUser.username,
        avatar: currentUser.avatar || 'https://via.placeholder.com/35',
        text: text,
        timestamp: new Date().toISOString()
    };
    
    fetch('api/add_comment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            post_id: postId,
            comment: comment
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (!post.comments) post.comments = [];
            post.comments.push(data.comment || comment);
            renderPosts();
            input.value = '';
            
            // Show comment section
            document.getElementById(`comments-${postId}`).style.display = 'block';
        }
    })
    .catch(error => {
        // Demo mode
        if (!post.comments) post.comments = [];
        post.comments.push(comment);
        renderPosts();
        input.value = '';
        document.getElementById(`comments-${postId}`).style.display = 'block';
    });
}

function sharePost(postId) {
    const post = posts.find(p => p.id === postId);
    if (!post) return;

    Swal.fire({
        title: 'Share Post',
        html: `
            <div class="text-start">
                <label for="sharePostText" class="form-label">Add text (optional)</label>
                <textarea id="sharePostText" class="form-control mb-3" rows="3" maxlength="500" placeholder="Say something about this post..."></textarea>
                <label for="sharePostPrivacy" class="form-label">Privacy</label>
                <select id="sharePostPrivacy" class="form-select">
                    <option value="only_me">Only me</option>
                    <option value="followers">Friends (followers)</option>
                    <option value="friends_of_friends">Friends of friends</option>
                    <option value="public" selected>Public</option>
                </select>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Share',
        confirmButtonColor: '#6366f1',
        preConfirm: () => {
            const content = (document.getElementById('sharePostText')?.value || '').trim();
            const privacy = document.getElementById('sharePostPrivacy')?.value || 'public';
            return { content, privacy };
        }
    }).then((result) => {
        if (!result.isConfirmed) return;
        const payload = result.value || { content: '', privacy: 'public' };

        fetch('api/share_post.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                post_id: postId,
                user_id: currentUser.id,
                content: payload.content,
                privacy: payload.privacy
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                post.shares = data.share_count || post.shares + 1;
                loadPosts();
                
                // Create notification
                addNotification({
                    type: 'share',
                    message: `${currentUser.name} shared your post`,
                    post_id: postId
                });
            } else {
                Swal.fire('Error', data.message || 'Failed to share post', 'error');
            }
        })
        .catch(error => {
            // Demo mode
            post.shares += 1;
            renderPosts();
            Swal.fire('Shared', 'Post shared locally (demo mode).', 'success');
        });
    });
}

function showPostMenu(postId) {
    Swal.fire({
        title: 'Post Options',
        showCancelButton: true,
        showDenyButton: true,
        confirmButtonText: 'Edit',
        denyButtonText: 'Delete',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#6366f1',
        denyButtonColor: '#ef4444'
    }).then((result) => {
        if (result.isConfirmed) {
            editPost(postId);
        } else if (result.isDenied) {
            deletePost(postId);
        }
    });
}

function editPost(postId) {
    const post = posts.find(p => p.id === postId);
    if (!post) return;

    resetEditPostState();
    editPostState.postId = postId;
    editPostState.existingMedia = normalizePostMedia(post).map((url, idx) => ({
        id: `existing-${idx}`,
        url,
        removed: false
    }));

    const contentInput = document.getElementById('editPostContent');
    if (contentInput) contentInput.value = post.content || '';
    renderEditPostMediaPreview();

    const modalEl = document.getElementById('editPostModal');
    if (!modalEl) return;
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}

function normalizePostMedia(post) {
    if (!post || !post.media) return [];
    return (Array.isArray(post.media) ? post.media : [post.media])
        .map((item) => (item && typeof item === 'object' ? item.url : item))
        .filter(Boolean);
}

function resetEditPostState() {
    editPostState.newMedia.forEach(item => {
        if (item.previewUrl) URL.revokeObjectURL(item.previewUrl);
    });
    editPostState = {
        postId: null,
        existingMedia: [],
        newMedia: [],
        nextNewId: 1
    };
    const fileInput = document.getElementById('editPostMediaInput');
    if (fileInput) fileInput.value = '';
    const preview = document.getElementById('editPostMediaPreview');
    if (preview) preview.innerHTML = '';
    const manager = document.getElementById('editPostMediaManagerList');
    if (manager) manager.innerHTML = '';
}

function getActiveEditMediaItems() {
    const existing = editPostState.existingMedia
        .filter(item => !item.removed)
        .map(item => ({ id: item.id, url: item.url, isNew: false, isVideo: isVideoMediaUrl(item.url) }));
    const newer = editPostState.newMedia
        .map(item => ({ id: item.id, url: item.previewUrl, isNew: true, isVideo: !!item.isVideo }));
    return [...existing, ...newer];
}

function renderEditPostMediaPreview() {
    const wrap = document.getElementById('editPostMediaPreview');
    if (!wrap) return;

    const items = getActiveEditMediaItems();
    if (items.length === 0) {
        wrap.innerHTML = `
            <div class="text-center py-3">
                <p class="text-muted mb-2">No media selected</p>
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="openEditPostMediaPicker()">
                    <i class="fas fa-plus"></i> Add Media
                </button>
            </div>
        `;
        return;
    }

    const first = items[0];
    const overlayBtn = items.length === 1
        ? `<button type="button" class="btn btn-sm btn-danger edit-media-overlay-btn" title="Remove media" onclick="removeEditMediaItem('${first.id}')"><i class="fas fa-trash"></i></button>`
        : `<button type="button" class="btn btn-sm btn-primary edit-media-overlay-btn" title="Edit media list" onclick="openEditPostMediaManager()"><i class="fas fa-pen"></i></button>`;

    const mediaHtml = first.isVideo
        ? `<video src="${first.url}" controls playsinline></video>`
        : `<img src="${first.url}" alt="Post media preview">`;

    const countBadge = items.length > 1
        ? `<span class="badge bg-dark position-absolute bottom-0 end-0 m-2">${items.length} files</span>`
        : '';

    wrap.innerHTML = `
        <div class="edit-media-single-wrap mb-3">
            ${overlayBtn}
            ${mediaHtml}
            ${countBadge}
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-outline-primary btn-sm" onclick="openEditPostMediaPicker()">
                <i class="fas fa-plus"></i> Add Media
            </button>
            ${items.length > 1 ? `
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="openEditPostMediaManager()">
                    <i class="fas fa-images"></i> Manage All Media
                </button>
            ` : ''}
        </div>
    `;
}

function openEditPostMediaPicker() {
    document.getElementById('editPostMediaInput')?.click();
}

function handleEditPostMediaSelect(e) {
    const files = Array.from(e.target.files || []);
    if (!files.length) return;

    const validIncoming = [];
    files.forEach(file => {
        if (validateFile(file, { allowVideo: true, maxSizeMB: 30 })) {
            validIncoming.push(file);
        }
    });
    if (!validIncoming.length) {
        e.target.value = '';
        return;
    }

    const nextTotalFiles = [
        ...editPostState.newMedia.map(item => item.file),
        ...validIncoming
    ];
    if (!validateTotalUploadSize(nextTotalFiles)) {
        e.target.value = '';
        return;
    }

    validIncoming.forEach(file => {
        const previewUrl = URL.createObjectURL(file);
        editPostState.newMedia.push({
            id: `new-${editPostState.nextNewId++}`,
            file,
            previewUrl,
            isVideo: file.type.startsWith('video/')
        });
    });

    e.target.value = '';
    renderEditPostMediaPreview();
    renderEditPostMediaManager();
}

function removeEditMediaItem(itemId) {
    if (!itemId) return;
    if (itemId.startsWith('existing-')) {
        const item = editPostState.existingMedia.find(m => m.id === itemId);
        if (item) item.removed = true;
    } else {
        const idx = editPostState.newMedia.findIndex(m => m.id === itemId);
        if (idx >= 0) {
            if (editPostState.newMedia[idx].previewUrl) {
                URL.revokeObjectURL(editPostState.newMedia[idx].previewUrl);
            }
            editPostState.newMedia.splice(idx, 1);
        }
    }
    renderEditPostMediaPreview();
    renderEditPostMediaManager();
}

function openEditPostMediaManager() {
    renderEditPostMediaManager();
    const modalEl = document.getElementById('editPostMediaManagerModal');
    if (!modalEl) return;
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}

function renderEditPostMediaManager() {
    const list = document.getElementById('editPostMediaManagerList');
    if (!list) return;

    const items = getActiveEditMediaItems();
    if (!items.length) {
        list.innerHTML = `
            <div class="col-12 text-center py-4">
                <p class="text-muted mb-2">No media to manage</p>
                <button type="button" class="btn btn-outline-primary" onclick="openEditPostMediaPicker()">
                    <i class="fas fa-plus"></i> Add Media
                </button>
            </div>
        `;
        return;
    }

    const cards = items.map(item => {
        const media = item.isVideo
            ? `<video src="${item.url}" controls playsinline></video>`
            : `<img src="${item.url}" alt="Post media">`;
        return `
            <div class="col-12">
                <div class="card edit-media-manager-item">
                    <div class="card-body">
                        <div class="d-flex justify-content-end mb-2">
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeEditMediaItem('${item.id}')">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        ${media}
                    </div>
                </div>
            </div>
        `;
    }).join('');

    const addCard = `
        <div class="col-12">
            <button type="button" class="btn btn-outline-primary w-100 py-3" onclick="openEditPostMediaPicker()">
                <i class="fas fa-plus"></i> Add More Media
            </button>
        </div>
    `;

    list.innerHTML = cards + addCard;
}

function saveEditedPost() {
    if (!editPostState.postId) return;

    const contentInput = document.getElementById('editPostContent');
    const saveBtn = document.getElementById('saveEditPostBtn');
    const content = contentInput ? contentInput.value.trim() : '';
    const activeMedia = getActiveEditMediaItems();
    if (!content && activeMedia.length === 0) {
        Swal.fire('Validation', 'Post must have text or media.', 'warning');
        return;
    }

    const newFiles = editPostState.newMedia.map(item => item.file);
    if (!validateTotalUploadSize(newFiles)) {
        return;
    }

    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';
    }

    const removedUrls = editPostState.existingMedia.filter(item => item.removed).map(item => item.url);
    const formData = new FormData();
    formData.append('post_id', String(editPostState.postId));
    formData.append('user_id', String(currentUser.id));
    formData.append('content', content);
    formData.append('removed_media_urls', JSON.stringify(removedUrls));
    editPostState.newMedia.forEach(item => formData.append('media[]', item.file));

    fetch('api/edit_post.php', {
        method: 'POST',
        body: formData
    })
    .then(async (response) => {
        const raw = await response.text();
        let data = null;
        try {
            data = JSON.parse(raw);
        } catch (e) {
            throw new Error((raw || 'Invalid server response').replace(/<[^>]*>/g, ' ').trim().slice(0, 300));
        }
        if (!response.ok) {
            throw new Error(data?.message || `Request failed with status ${response.status}`);
        }
        return data;
    })
    .then(data => {
        if (!data.success) {
            throw new Error(data.message || 'Failed to update post');
        }

        const localPost = posts.find(p => p.id === editPostState.postId);
        if (localPost) {
            localPost.content = content;
            if (data.post && Array.isArray(data.post.media)) {
                localPost.media = data.post.media;
                localPost.media_type = data.post.media_type || (data.post.media.length ? 'image' : 'text');
            } else {
                localPost.media = activeMedia.map(item => item.url);
                localPost.media_type = localPost.media.length ? 'image' : 'text';
            }
        }

        renderPosts();
        const mediaManagerModalEl = document.getElementById('editPostMediaManagerModal');
        const editPostModalEl = document.getElementById('editPostModal');
        if (mediaManagerModalEl) bootstrap.Modal.getOrCreateInstance(mediaManagerModalEl).hide();
        if (editPostModalEl) bootstrap.Modal.getOrCreateInstance(editPostModalEl).hide();
        Swal.fire('Updated!', 'Your post has been updated.', 'success');
    })
    .catch((error) => {
        Swal.fire('Error', error.message || 'Failed to update post', 'error');
    })
    .finally(() => {
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
        }
    });
}

function deletePost(postId) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6366f1',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('api/delete_post.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    post_id: postId,
                    user_id: currentUser.id
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    posts = posts.filter(p => p.id !== postId);
                    applyLocalPostCountDelta(-1);
                    renderPosts();
                    loadMyProfileStats();
                    Swal.fire('Deleted!', 'Your post has been deleted.', 'success');
                } else {
                    Swal.fire('Error', data.message || 'Failed to delete post', 'error');
                }
            })
            .catch(error => {
                // Demo mode
                posts = posts.filter(p => p.id !== postId);
                applyLocalPostCountDelta(-1);
                renderPosts();
                loadMyProfileStats();
                Swal.fire('Deleted!', 'Your post has been deleted.', 'success');
            });
        }
    });
}

function toggleNotificationPanel() {
    const panel = document.getElementById('notificationPanel');
    panel.classList.toggle('active');
}

function loadNotifications() {
    fetch(`api/get_notifications.php?user_id=${currentUser.id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const seen = loadReadNotifications();
                notifications = data.notifications.map(n => ({
                    ...n,
                    read: n.read || seen.has(n.id)
                }));
                renderNotifications();
            }
        })
        .catch(error => {
            // Demo notifications
            notifications = [];
            renderNotifications();
        });
}

function renderNotifications() {
    const container = document.getElementById('notificationContent');
    const badge = document.getElementById('notificationBadge');
    
    const unreadCount = notifications.filter(n => !n.read).length;
    badge.textContent = unreadCount;
    badge.style.display = unreadCount > 0 ? 'flex' : 'none';
    
    if (notifications.length === 0) {
        container.innerHTML = '<div class="text-center py-5"><p class="text-muted">No notifications yet</p></div>';
        return;
    }
    
    container.innerHTML = notifications.map((notif, idx) => `
        <div class="notification-item ${notif.read ? '' : 'unread'}" onclick="openNotification(${idx})">
            <div class="notification-item-content">
                <div class="notification-message">
                    ${escapeHtml(notif.message)}
                    ${notif.type === 'follow' ? '<span class="notif-tag">Follow back</span>' : ''}
                    ${notif.type === 'warning' ? '<span class="notif-tag notif-warning"><i class="fas fa-exclamation-triangle"></i> Warning</span>' : ''}
                </div>
                <div class="notification-time">${getTimeAgo(notif.timestamp)}</div>
            </div>
            <div class="notification-actions" onclick="event.stopPropagation()">
                <button class="btn-notification-toggle" onclick="toggleNotificationRead(${notif.id}, ${idx}, ${notif.read ? 'false' : 'true'})" title="${notif.read ? 'Mark as unread' : 'Mark as read'}">
                    <i class="fas ${notif.read ? 'fa-envelope' : 'fa-envelope-open'}"></i>
                </button>
            </div>
        </div>
    `).join('');
}

function addNotification(notification) {
    const newNotif = {
        id: Date.now(),
        ...notification,
        read: false,
        timestamp: new Date().toISOString()
    };
    notifications.unshift(newNotif);
    renderNotifications();
}

function openNotification(idx) {
    const notif = notifications[idx];
    if (!notif) return;

    // mark as read locally and persist
    if (!notif.read) {
        notif.read = true;
        markNotificationRead(notif.id, true);
    }

    // close panel for better UX
    document.getElementById('notificationPanel')?.classList.remove('active');

    // Deleted post: show modal with removed post content (direct to deleted post view)
    if (notif.type === 'post_deleted') {
        const content = notif.post_content_snapshot || '(No text content)';
        Swal.fire({
            title: 'Post removed by admin',
            html: '<div class="text-start p-3 bg-light rounded"><p class="mb-0 text-muted">' + escapeHtml(content) + '</p></div><p class="small text-muted mt-2">This post was removed by an administrator.</p>',
            icon: 'info',
            confirmButtonColor: '#6366f1'
        });
        return;
    }

    // Like, comment, share: go to post in feed
    if (notif.post_id && notif.type !== 'post_deleted') {
        isNewUser = false;
        focusPostId = notif.post_id;
        currentView = 'home';
        currentFilterUserId = null;
        updateStoriesVisibility();
        setActiveNav('home');
        if (posts && posts.length) {
            renderPosts();
        } else {
            loadPosts();
        }
        return;
    }

    if (notif.type === 'follow' && notif.from_user_id) {
        openUserProfile(notif.from_user_id);
        return;
    }

    if (notif.type === 'story' && notif.from_user_id) {
        loadStories();
        setTimeout(() => {
            const u = storyUsers.find(x => x.user_id === notif.from_user_id);
            if (u && u.stories && u.stories.length) openStoryViewer(notif.from_user_id);
        }, 300);
        return;
    }

    if (notif.type === 'story_like') {
        closeBurgerMenu();
        openArchiveStoriesModal();  // View archived stories (story may have expired)
        return;
    }
}

function markAllNotifications(markAsRead) {
    fetch('api/mark_notification_read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            user_id: currentUser.id,
            mark_all: true,
            mark_as_read: markAsRead
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Update local notifications
            notifications.forEach(n => {
                n.read = markAsRead;
            });
            renderNotifications();
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: data.message,
                timer: 1500,
                showConfirmButton: false
            });
        }
    })
    .catch(() => {
        Swal.fire('Error', 'Failed to update notifications', 'error');
    });
}

function toggleNotificationRead(notificationId, idx, markAsRead) {
    fetch('api/mark_notification_read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            user_id: currentUser.id,
            notification_id: notificationId,
            mark_as_read: markAsRead
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Update local notification
            if (notifications[idx]) {
                notifications[idx].read = markAsRead;
            }
            renderNotifications();
        }
    })
    .catch(() => {
        Swal.fire('Error', 'Failed to update notification', 'error');
    });
}

function markNotificationRead(notificationId, markAsRead) {
    // Silent update - no user feedback needed
    fetch('api/mark_notification_read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            user_id: currentUser.id,
            notification_id: notificationId,
            mark_as_read: markAsRead
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            persistReadNotification(notificationId);
        }
    })
    .catch(() => {});
}

function loadReadNotifications() {
    try {
        const raw = localStorage.getItem(READ_NOTIFS_KEY);
        if (!raw) return new Set();
        const arr = JSON.parse(raw);
        if (!Array.isArray(arr)) return new Set();
        return new Set(arr);
    } catch (e) {
        return new Set();
    }
}

function persistReadNotification(id) {
    if (!id) return;
    const set = loadReadNotifications();
    set.add(id);
    try {
        localStorage.setItem(READ_NOTIFS_KEY, JSON.stringify(Array.from(set)));
    } catch (e) {}
}


function openBurgerMenu() {
    document.getElementById('burgerDrawer')?.classList.add('active');
    document.getElementById('burgerOverlay')?.classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeBurgerMenu() {
    document.getElementById('burgerDrawer')?.classList.remove('active');
    document.getElementById('burgerOverlay')?.classList.remove('active');
    document.body.style.overflow = '';
}
function openChangePasswordModal() {
    Swal.fire({
        title: 'Change Password',
        html: `
            <input type="password" id="cpCurrent" class="form-control mb-2" placeholder="Current password">
            <input type="password" id="cpNew" class="form-control mb-2" placeholder="New password (10+ chars, upper, lower, numbers, no special)">
            <input type="password" id="cpConfirm" class="form-control" placeholder="Confirm new password">
        `,
        confirmButtonText: 'Update',
        confirmButtonColor: '#6366f1',
        showCancelButton: true,
        preConfirm: () => {
            const cur = document.getElementById('cpCurrent').value;
            const neu = document.getElementById('cpNew').value;
            const conf = document.getElementById('cpConfirm').value;
            if (!cur || !neu || !conf) { Swal.showValidationMessage('Fill all fields'); return false; }
            if (neu.length < 10 || !/[A-Z]/.test(neu) || !/[a-z]/.test(neu) || !/[0-9]/.test(neu) || /[^A-Za-z0-9]/.test(neu)) {
                Swal.showValidationMessage('New password: 10+ chars, upper, lower, numbers, no special'); return false;
            }
            if (neu !== conf) { Swal.showValidationMessage('New passwords do not match'); return false; }
            return { current: cur, new: neu };
        }
    }).then((r) => {
        if (r.isConfirmed && r.value) {
            fetch('api/change_password.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: currentUser.id, current_password: r.value.current, new_password: r.value.new })
            }).then(res => res.json()).then(data => {
                if (data.success) Swal.fire('Success', 'Password updated.', 'success');
                else Swal.fire('Error', data.message || 'Failed to update password', 'error');
            }).catch(() => Swal.fire('Error', 'Failed to update password', 'error'));
        }
    });
}
function openChangeUsernameModal() {
    Swal.fire({
        title: 'Change Username',
        html: `
            <input type="password" id="cuPassword" class="form-control mb-2" placeholder="Current password">
            <input type="text" id="cuNewUsername" class="form-control" placeholder="New username (3-50 chars, letters, numbers, underscore)" value="${escapeHtml(currentUser.username || '')}" autocomplete="username">
        `,
        confirmButtonText: 'Update',
        confirmButtonColor: '#6366f1',
        showCancelButton: true,
        preConfirm: () => {
            const pw = document.getElementById('cuPassword').value;
            const nu = (document.getElementById('cuNewUsername').value || '').trim();
            if (!pw) { Swal.showValidationMessage('Enter your password'); return false; }
            if (!nu) { Swal.showValidationMessage('Enter new username'); return false; }
            if (nu.length < 3) { Swal.showValidationMessage('Username must be at least 3 characters'); return false; }
            if (nu.length > 50) { Swal.showValidationMessage('Username must be 50 characters or less'); return false; }
            if (!/^[A-Za-z0-9_]+$/.test(nu)) { Swal.showValidationMessage('Username can only contain letters, numbers and underscore'); return false; }
            if (nu === (currentUser.username || '')) { Swal.showValidationMessage('New username is the same as current'); return false; }
            return { password: pw, new_username: nu };
        }
    }).then((r) => {
        if (r.isConfirmed && r.value) {
            fetch('api/change_username.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: currentUser.id, password: r.value.password, new_username: r.value.new_username })
            }).then(res => res.json()).then(data => {
                if (data.success) {
                    currentUser.username = data.username;
                    localStorage.setItem('user', JSON.stringify(currentUser));
                    updateProfileSidebar(viewedProfile && viewedProfile.id === currentUser.id ? { ...viewedProfile, username: data.username } : viewedProfile);
                    if (viewedProfile && viewedProfile.id === currentUser.id) viewedProfile.username = data.username;
                    Swal.fire('Success', 'Username updated.', 'success');
                } else Swal.fire('Error', data.message || 'Failed to update username', 'error');
            }).catch(() => Swal.fire('Error', 'Failed to update username', 'error'));
        }
    });
}
function openPrivacySettingsModal() {
    Swal.fire({
        title: 'Privacy Settings',
        html: '<p class="text-muted">Control who can see your posts. You can set default visibility in the post composer (Only me, Friends, Friends of friends, Public).</p>',
        confirmButtonColor: '#6366f1'
    });
}
function openBlockedUsersModal() {
    Swal.fire({
        title: 'Blocked Users',
        html: '<p class="text-muted">Blocked users list. Feature can be extended with a dedicated API.</p>',
        confirmButtonColor: '#6366f1'
    });
}
function openReportModal() {
    Swal.fire({
        title: 'Report User/Post',
        html: '<p class="text-muted">To report a post, use the menu (⋯) on the post and choose Report. To report a user, visit their profile and use Report.</p>',
        confirmButtonColor: '#6366f1'
    });
}
function openCommunityGuidelinesModal() {
    Swal.fire({
        title: 'Community Guidelines',
        html: `
            <div class="text-start text-muted small">
                <p>• Be respectful. No harassment or hate speech.</p>
                <p>• No spam or misleading content.</p>
                <p>• Keep campus-related discussions constructive.</p>
                <p>• Admins may remove content that violates these guidelines.</p>
            </div>
        `,
        confirmButtonColor: '#6366f1',
        width: '480px'
    });
}
function handleLogout() {
    Swal.fire({
        title: 'Logout?',
        text: 'Are you sure you want to logout?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#6366f1',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Yes, logout'
    }).then((result) => {
        if (result.isConfirmed) {
            localStorage.removeItem('user');
            window.location.href = 'index.html';
        }
    });
}

function getTimeAgo(timestamp) {
    const now = new Date();
    const past = new Date(timestamp);
    const diff = Math.floor((now - past) / 1000);
    
    if (diff < 60) return 'just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`;
    return past.toLocaleDateString();
}

function privacyIcon(privacy) {
    const map = { only_me: 'fa-lock', followers: 'fa-user-friends', friends_of_friends: 'fa-users', public: 'fa-globe-americas', private: 'fa-lock' };
    return map[privacy] || 'fa-globe-americas';
}
function privacyLabel(privacy) {
    const map = { only_me: 'Only me', followers: 'Friends (followers)', friends_of_friends: 'Friends of friends', public: 'Public', private: 'Only me' };
    return map[privacy] || 'Public';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ===== MYDAY / STORIES (24h) =====
let storyUsers = [];

function loadStories() {
    return fetch(`api/get_stories.php?viewer_id=${currentUser.id}`)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.story_users) {
                storyUsers = data.story_users;
                renderStories();
            }
        })
        .catch(() => { storyUsers = []; renderStories(); });
}

function renderStories() {
    const list = document.getElementById('storiesList');
    if (!list) return;
    list.innerHTML = storyUsers.map(u => {
        const last = u.stories[u.stories.length - 1];
        const url = last ? last.media_url : '';
        return `<div class="story-circle" onclick="openStoryViewer(${u.user_id})" title="${escapeHtml(u.name)}">
            <div class="story-circle-inner"><img src="${url || u.avatar}" alt="" onerror="this.src='https://via.placeholder.com/56'"></div>
        </div>`;
    }).join('');
}

function handleStoryUpload(e) {
    const file = e.target.files && e.target.files[0];
    if (!file) return;
    const maxStorySize = 30 * 1024 * 1024; // 30MB
    if ((file.size || 0) > maxStorySize) {
        Swal.fire('Error', 'Story file exceeds 30MB limit', 'error');
        e.target.value = '';
        return;
    }
    const formData = new FormData();
    formData.append('user_id', currentUser.id);
    formData.append('media', file);
    fetch('api/create_story.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Swal.fire({ icon: 'success', title: 'My Day updated', text: 'Your story is live for 24 hours.', timer: 2000, showConfirmButton: false });
                loadStories();
            } else Swal.fire('Error', data.message || 'Upload failed', 'error');
        })
        .catch(() => Swal.fire('Error', 'Upload failed', 'error'));
    e.target.value = '';
}

let currentStoryData = null; // { story, user, storyIndex }
let storyViewerIndex = 0;

function openStoryViewer(userId, startIndex) {
    const u = storyUsers.find(x => x.user_id === userId);
    if (!u || !u.stories || u.stories.length === 0) return;
    storyViewerIndex = startIndex !== undefined ? Math.min(startIndex, u.stories.length - 1) : 0;
    showStoryAtIndex(u, storyViewerIndex);
    const modal = new bootstrap.Modal(document.getElementById('storyViewerModal'));
    modal.show();
}

function showStoryAtIndex(u, idx) {
    if (idx < 0 || idx >= u.stories.length) return;
    const s = u.stories[idx];
    storyViewerIndex = idx;
    currentStoryData = { story: s, user: u, storyIndex: idx };
    document.getElementById('storyViewerName').textContent = u.name;
    document.getElementById('storyViewerTime').textContent = getTimeAgo(s.created_at) + (u.stories.length > 1 ? ` (${idx + 1}/${u.stories.length})` : '');
    const imgEl = document.getElementById('storyViewerImage');
    const vidEl = document.getElementById('storyViewerVideo');
    if (s.media_type === 'video') {
        imgEl.classList.add('d-none'); vidEl.classList.remove('d-none');
        vidEl.src = s.media_url;
        vidEl.onloadeddata = () => vidEl.play();
    } else {
        vidEl.classList.add('d-none'); imgEl.classList.remove('d-none');
        imgEl.src = s.media_url;
    }
    // Progress bars for multiple stories
    const barsEl = document.getElementById('storyProgressBars');
    if (barsEl && u.stories.length > 1) {
        barsEl.innerHTML = u.stories.map((_, i) => `<div class="story-progress-bar"><div class="story-progress-fill ${i <= idx ? 'filled' : ''} ${i === idx ? 'active' : ''}"></div></div>`).join('');
        barsEl.style.display = 'flex';
    } else if (barsEl) barsEl.style.display = 'none';
    // Prev/Next buttons
    const prevBtn = document.getElementById('storyPrevBtn');
    const nextBtn = document.getElementById('storyNextBtn');
    if (prevBtn) prevBtn.style.display = idx > 0 ? 'flex' : 'none';
    if (nextBtn) nextBtn.style.display = idx < u.stories.length - 1 ? 'flex' : 'none';
    if (prevBtn) prevBtn.onclick = () => showStoryAtIndex(u, idx - 1);
    if (nextBtn) nextBtn.onclick = () => showStoryAtIndex(u, idx + 1);
    // Record view
    fetch('api/record_story_view.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ story_id: s.id, viewer_id: currentUser.id })
    }).catch(() => {});
    // Like button
    const likeBtn = document.getElementById('storyLikeBtn');
    const likeIcon = document.getElementById('storyLikeIcon');
    const likeCount = document.getElementById('storyLikeCount');
    likeBtn.style.display = 'inline-block';
    likeBtn.onclick = () => toggleStoryLike(s.id);
    likeIcon.className = s.liked ? 'fas fa-heart text-danger' : 'far fa-heart';
    likeCount.textContent = s.like_count || 0;
    // Viewers button (only for my story)
    const viewersBtn = document.getElementById('storyViewersBtn');
    if (u.user_id === currentUser.id) {
        viewersBtn.style.display = 'inline-block';
        viewersBtn.onclick = () => showStoryViewers(s.id, u.user_id);
        fetch(`api/get_story_viewers.php?story_id=${s.id}&owner_id=${u.user_id}`)
            .then(r => r.json())
            .then(d => {
                if (d.success) document.getElementById('storyViewerCount').textContent = d.viewers.length;
            });
    } else {
        viewersBtn.style.display = 'none';
    }
}

function toggleStoryLike(storyId) {
    fetch('api/like_story.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ story_id: storyId, user_id: currentUser.id })
    })
        .then(r => r.json())
        .then(data => {
            if (data.success && currentStoryData && currentStoryData.story.id === storyId) {
                currentStoryData.story.liked = data.liked;
                currentStoryData.story.like_count = (currentStoryData.story.like_count || 0) + (data.liked ? 1 : -1);
                document.getElementById('storyLikeIcon').className = data.liked ? 'fas fa-heart text-danger' : 'far fa-heart';
                document.getElementById('storyLikeCount').textContent = currentStoryData.story.like_count;
            }
        });
}

function showStoryViewers(storyId, ownerId) {
    fetch(`api/get_story_viewers.php?story_id=${storyId}&owner_id=${ownerId}`)
        .then(r => r.json())
        .then(data => {
            const list = document.getElementById('storyViewersList');
            if (!data.success || !data.viewers || data.viewers.length === 0) {
                list.innerHTML = '<div class="p-3 text-muted small">No viewers yet</div>';
            } else {
                list.innerHTML = data.viewers.map(v => `
                    <div class="d-flex align-items-center gap-2 p-2 border-bottom border-secondary">
                        <img src="${v.avatar}" class="rounded-circle" style="width:32px;height:32px" onerror="this.src='https://via.placeholder.com/32'">
                        <div class="flex-grow-1">
                            <div class="small fw-bold">${escapeHtml(v.name)}</div>
                            <div class="small text-white-50">@${escapeHtml(v.username)}</div>
                        </div>
                    </div>
                `).join('');
            }
            new bootstrap.Modal(document.getElementById('storyViewersModal')).show();
        });
}

// ===== ARCHIVE STORIES =====
let archivedStories = [];
let archiveStoryIndex = 0;

function openArchiveStoriesModal() {
    const modal = new bootstrap.Modal(document.getElementById('archiveStoriesModal'));
    document.getElementById('archiveEmptyState').style.display = 'none';
    document.getElementById('archiveViewerImage').classList.add('d-none');
    document.getElementById('archiveViewerVideo').classList.add('d-none');
    document.getElementById('archiveProgressBars').style.display = 'none';
    document.getElementById('archivePrevBtn').style.display = 'none';
    document.getElementById('archiveNextBtn').style.display = 'none';
    modal.show();
    fetch(`api/get_archived_stories.php?user_id=${currentUser.id}`)
        .then(r => r.json())
        .then(data => {
            archivedStories = data.success ? (data.stories || []) : [];
            if (archivedStories.length === 0) {
                document.getElementById('archiveEmptyState').style.display = 'block';
            } else {
                archiveStoryIndex = 0;
                showArchiveStoryAtIndex(archiveStoryIndex);
            }
        })
        .catch(() => {
            archivedStories = [];
            document.getElementById('archiveEmptyState').style.display = 'block';
        });
}

function showArchiveStoryAtIndex(idx) {
    if (archivedStories.length === 0) return;
    if (idx < 0 || idx >= archivedStories.length) return;
    archiveStoryIndex = idx;
    const s = archivedStories[idx];
    const imgEl = document.getElementById('archiveViewerImage');
    const vidEl = document.getElementById('archiveViewerVideo');
    document.getElementById('archiveEmptyState').style.display = 'none';
    if (s.media_type === 'video') {
        imgEl.classList.add('d-none'); vidEl.classList.remove('d-none');
        vidEl.src = s.media_url;
        vidEl.onloadeddata = () => vidEl.play();
    } else {
        vidEl.classList.add('d-none'); imgEl.classList.remove('d-none');
        imgEl.src = s.media_url;
    }
    const barsEl = document.getElementById('archiveProgressBars');
    if (archivedStories.length > 1) {
        barsEl.innerHTML = archivedStories.map((_, i) => `<div class="story-progress-bar"><div class="story-progress-fill ${i <= idx ? 'filled' : ''} ${i === idx ? 'active' : ''}"></div></div>`).join('');
        barsEl.style.display = 'flex';
    } else barsEl.style.display = 'none';
    const prevBtn = document.getElementById('archivePrevBtn');
    const nextBtn = document.getElementById('archiveNextBtn');
    prevBtn.style.display = idx > 0 ? 'flex' : 'none';
    nextBtn.style.display = idx < archivedStories.length - 1 ? 'flex' : 'none';
    prevBtn.onclick = () => showArchiveStoryAtIndex(idx - 1);
    nextBtn.onclick = () => showArchiveStoryAtIndex(idx + 1);
}

// ===== NOTES =====
let notes = [];
let currentEditingNoteId = null;

function openCreateNoteModal() {
    currentEditingNoteId = null;
    document.getElementById('noteFormModalTitle').innerHTML = '<i class="fas fa-sticky-note"></i> New Note';
    document.getElementById('noteFormContent').value = '';
    document.getElementById('noteFormContent').readOnly = false;
    const cc = document.getElementById('noteCharCount');
    if (cc) cc.textContent = '0';
    const modal = new bootstrap.Modal(document.getElementById('noteFormModal'));
    modal.show();
    setTimeout(function() { document.getElementById('noteFormContent').focus(); }, 300);
}

function openEditNoteModal(noteId) {
    const n = notes.find(x => x.id === noteId);
    if (!n) return;
    currentEditingNoteId = noteId;
    document.getElementById('noteFormModalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Note';
    document.getElementById('noteFormContent').value = n.content || '';
    document.getElementById('noteFormContent').readOnly = false;
    const cc = document.getElementById('noteCharCount');
    if (cc) cc.textContent = (n.content || '').length;
    const modal = new bootstrap.Modal(document.getElementById('noteFormModal'));
    modal.show();
    setTimeout(function() { document.getElementById('noteFormContent').focus(); }, 300);
}

function saveNoteFromForm() {
    const contentEl = document.getElementById('noteFormContent');
    const content = (contentEl && contentEl.value || '').trim();
    if (!content) {
        Swal.fire('Required', 'Please type your note.', 'warning');
        return;
    }
    if (content.length > 60) {
        Swal.fire('Limit', 'Note must be 60 characters or less.', 'warning');
        return;
    }
    const btn = document.getElementById('noteFormSaveBtn');
    if (btn) { btn.disabled = true; btn.textContent = 'Saving...'; }
    if (currentEditingNoteId == null) {
        fetch('api/create_note.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: currentUser.id, content: content })
        }).then(res => res.json()).then(data => {
            if (btn) { btn.disabled = false; btn.textContent = 'Save'; }
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('noteFormModal')).hide();
                Swal.fire('Saved', 'Note created.', 'success');
                loadNotes();
                loadMyProfileStats();
            } else Swal.fire('Error', data.message || 'Failed to create note', 'error');
        }).catch(() => {
            if (btn) { btn.disabled = false; btn.textContent = 'Save'; }
            Swal.fire('Error', 'Could not create note. Check connection.', 'error');
        });
    } else {
        fetch('api/update_note.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ note_id: currentEditingNoteId, user_id: currentUser.id, content: content })
        }).then(res => res.json()).then(data => {
            if (btn) { btn.disabled = false; btn.textContent = 'Save'; }
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('noteFormModal')).hide();
                Swal.fire('Saved', 'Note updated.', 'success');
                loadNotes();
                loadMyProfileStats();
            } else Swal.fire('Error', data.message || 'Failed to update note', 'error');
        }).catch(() => {
            if (btn) { btn.disabled = false; btn.textContent = 'Save'; }
            Swal.fire('Error', 'Could not update note. Check connection.', 'error');
        });
    }
}

function openNotesModal() {
    const el = document.getElementById('notesModal');
    if (!el) return;
    const modal = bootstrap.Modal.getOrCreateInstance(el);
    modal.show();
    document.getElementById('notesListContainer').innerHTML = '<div class="text-center py-3"><span class="spinner-border spinner-border-sm"></span> Loading notes...</div>';
    loadNotes();
}

function loadNotes() {
    const container = document.getElementById('notesListContainer');
    if (!container) return;
    fetch(`api/get_notes.php?user_id=${currentUser.id}&viewer_id=${currentUser.id}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) notes = data.notes || [];
            else notes = [];
            renderNotes();
        })
        .catch(() => {
            notes = [];
            container.innerHTML = '<p class="text-danger">Could not load notes. Check your connection and try again.</p><button type="button" class="btn btn-sm btn-primary" onclick="loadNotes()">Retry</button>';
        });
}

function renderNotes() {
    const container = document.getElementById('notesListContainer');
    if (!container) return;
    if (notes.length === 0) {
        container.innerHTML = '<p class="text-muted">No notes yet. Create one!</p>';
        return;
    }
    container.innerHTML = notes.map(n => `
        <div class="card mb-2">
            <div class="card-body py-2">
                <p class="card-text mb-2">${escapeHtml(n.content || '')}</p>
                <div class="d-flex gap-2 align-items-center">
                    <button class="btn btn-sm btn-outline-primary" onclick="openEditNoteModal(${n.id})"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteNote(${n.id})"><i class="fas fa-trash"></i></button>
                    <span class="small text-muted ms-auto"><i class="far fa-heart ${n.liked ? 'fas text-danger' : ''}"></i> ${n.like_count || 0}</span>
                </div>
            </div>
        </div>
    `).join('');
}

/* Create/Edit Note now use Bootstrap modal #noteFormModal - see openCreateNoteModal, openEditNoteModal, saveNoteFromForm */

function deleteNote(noteId) {
    Swal.fire({ title: 'Delete note?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444' }).then((r) => {
        if (r.isConfirmed) {
            fetch('api/delete_note.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ note_id: noteId, user_id: currentUser.id })
            }).then(res => res.json()).then(data => {
                if (data.success) { loadNotes(); loadMyProfileStats(); Swal.fire('Deleted', '', 'success'); }
            });
        }
    });
}

// ===== ADMIN FUNCTIONS =====

function loadAdminData() {
    if (!isModeratorRole()) {
        return;
    }
    renderAccounts();
    renderStudentActivities();
    renderReports();
    if (isAdminRole()) {
        renderAnnouncements();
        renderLogs();
    }
}

function renderStudentActivities() {
    const container = document.getElementById('studentActivitiesList');
    if (!container) return;
    
    container.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
    
    fetch(`api/admin_get_student_activities.php?admin_id=${currentUser.id}&limit=50`)
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.activities || data.activities.length === 0) {
                container.innerHTML = '<div class="text-center py-4 text-muted">No student activities found</div>';
                return;
            }
            
            const admin = isAdminRole();
            container.innerHTML = data.activities.map(activity => {
                const timeAgo = getTimeAgo(activity.timestamp);
                const warningBadge = activity.warnings > 0 
                    ? `<span class="badge bg-warning text-dark ms-2">${activity.warnings} Warning${activity.warnings > 1 ? 's' : ''}</span>` 
                    : '';
                const statusBadge = activity.status === 'banned' 
                    ? '<span class="badge bg-danger ms-2">Banned</span>' 
                    : '';
                
                if (activity.type === 'post') {
                    return `
                        <div class="admin-activity-item">
                            <div class="d-flex align-items-start gap-3">
                                <img src="${activity.avatar}" class="admin-activity-avatar" onerror="this.src='https://via.placeholder.com/40'">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <strong class="text-dark">${escapeHtml(activity.name)}</strong>
                                        <span class="text-dark">@${escapeHtml(activity.username)}</span>
                                        ${warningBadge}
                                        ${statusBadge}
                                    </div>
                                    <div class="admin-activity-content">${escapeHtml(activity.content)}</div>
                                    <div class="admin-activity-meta">
                                        <span class="text-muted">${timeAgo}</span>
                                        <span class="badge bg-info ms-2">Post</span>
                                    </div>
                                    <div class="admin-activity-actions mt-2">
                                        <button class="btn btn-sm btn-warning" onclick="adminWarnUser(${activity.user_id}, ${activity.id}, 'post')">
                                            <i class="fas fa-exclamation-triangle"></i> Warn
                                        </button>
                                        ${admin ? `<button class="btn btn-sm btn-danger" onclick="adminBanUser(${activity.user_id})">
                                            <i class="fas fa-ban"></i> Ban
                                        </button>` : ''}
                                        ${admin ? `<button class="btn btn-sm btn-outline-warning" onclick="adminLockUser(${activity.user_id})">
                                            <i class="fas fa-lock"></i> Lock
                                        </button>` : ''}
                                        <button class="btn btn-sm btn-outline-danger" onclick="adminDeletePost(${activity.id})">
                                            <i class="fas fa-trash"></i> Delete Post
                                        </button>
                                        ${admin ? `<button class="btn btn-sm btn-outline-danger" onclick="adminDeleteUser(${activity.user_id})">
                                            <i class="fas fa-user-times"></i> Delete Account
                                        </button>` : ''}
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                } else if (activity.type === 'comment') {
                    return `
                        <div class="admin-activity-item">
                            <div class="d-flex align-items-start gap-3">
                                <img src="${activity.avatar}" class="admin-activity-avatar" onerror="this.src='https://via.placeholder.com/40'">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <strong class="text-dark">${escapeHtml(activity.name)}</strong>
                                        <span class="text-dark">@${escapeHtml(activity.username)}</span>
                                        ${warningBadge}
                                        ${statusBadge}
                                    </div>
                                    <div class="admin-activity-content">${escapeHtml(activity.content)}</div>
                                    <div class="admin-activity-meta">
                                        <span class="text-muted">${timeAgo}</span>
                                        <span class="badge bg-secondary ms-2">Comment</span>
                                        <small class="text-muted ms-2">on post: ${escapeHtml(activity.post_content)}</small>
                                    </div>
                                    <div class="admin-activity-actions mt-2">
                                        <button class="btn btn-sm btn-warning" onclick="adminWarnUser(${activity.user_id}, ${activity.id}, 'comment')">
                                            <i class="fas fa-exclamation-triangle"></i> Warn
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="adminDeletePost(${activity.post_id})">
                                            <i class="fas fa-trash"></i> Remove Related Post
                                        </button>
                                        ${admin ? `<button class="btn btn-sm btn-danger" onclick="adminBanUser(${activity.user_id})">
                                            <i class="fas fa-ban"></i> Ban
                                        </button>` : ''}
                                        ${admin ? `<button class="btn btn-sm btn-outline-warning" onclick="adminLockUser(${activity.user_id})">
                                            <i class="fas fa-lock"></i> Lock
                                        </button>` : ''}
                                        ${admin ? `<button class="btn btn-sm btn-outline-danger" onclick="adminDeleteUser(${activity.user_id})">
                                            <i class="fas fa-user-times"></i> Delete Account
                                        </button>` : ''}
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                }
                return '';
            }).join('');
        })
        .catch(err => {
            container.innerHTML = '<div class="text-center py-4 text-danger">Error loading activities</div>';
        });
}

function renderAccounts() {
    const container = document.getElementById('accountsList');
    if (!container) return;
    const admin = isAdminRole();
    
    container.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
    
    fetch(`api/admin_get_accounts.php?admin_id=${currentUser.id}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.accounts || data.accounts.length === 0) {
                container.innerHTML = '<div class="text-center py-4 text-muted">No student or faculty accounts found</div>';
                return;
            }
            
            container.innerHTML = `
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>@Username</th>
                                <th>Email</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Warnings</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.accounts.map(a => `
                                ${(() => {
                                    const isTargetAdmin = a.account_type === 'admin';
                                    const showRoleDropdown = admin && !isTargetAdmin;
                                    const showWarn = !isTargetAdmin;
                                    const showBan = admin && !isTargetAdmin;
                                    return `
                                <tr>
                                    <td>${escapeHtml(a.name || '-')}</td>
                                    <td>@${escapeHtml(a.username || '')}</td>
                                    <td>${escapeHtml(a.email || '')}</td>
                                    <td><span class="badge ${a.account_type === 'admin' ? 'bg-danger' : (a.account_type === 'faculty' ? 'bg-info' : 'bg-secondary')}">${a.account_type}</span></td>
                                    <td><span class="badge bg-${a.status === 'active' ? 'success' : 'danger'}">${a.status}</span></td>
                                    <td>${a.warnings}</td>
                                    <td>${getTimeAgo(a.created_at)}</td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" onclick="openUserProfile(${a.id})">View</button>
                                        ${showRoleDropdown ? `
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                                    Role
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <button class="dropdown-item ${a.account_type === 'student' ? 'active' : ''}" onclick="adminChangeUserRole(${a.id}, 'student', '${a.account_type}')">
                                                            ${a.account_type === 'student' ? '<i class="fas fa-check me-2"></i>' : '<span class="me-3"></span>'}Student
                                                        </button>
                                                    </li>
                                                    <li>
                                                        <button class="dropdown-item ${a.account_type === 'faculty' ? 'active' : ''}" onclick="adminChangeUserRole(${a.id}, 'faculty', '${a.account_type}')">
                                                            ${a.account_type === 'faculty' ? '<i class="fas fa-check me-2"></i>' : '<span class="me-3"></span>'}Faculty
                                                        </button>
                                                    </li>
                                                </ul>
                                            </div>
                                        ` : ''}
                                        ${showWarn ? `<button class="btn btn-sm btn-outline-warning" onclick="adminWarnUser(${a.id}, 0, 'account')">Warn</button>` : ''}
                                        ${showBan ? `<button class="btn btn-sm btn-outline-danger" onclick="adminBanUser(${a.id})">Ban</button>` : ''}
                                    </td>
                                </tr>
                                `;
                                })()}
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        })
        .catch(() => { container.innerHTML = '<div class="text-center py-4 text-danger">Error loading accounts</div>'; });
}

function renderAnnouncements() {
    const container = document.getElementById('announcementsList');
    if (!container) return;
    
    container.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
    
    fetch(`api/admin_get_announcements.php?admin_id=${currentUser.id}&status=all`)
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.announcements || data.announcements.length === 0) {
                container.innerHTML = '<div class="text-center py-4 text-muted">No announcements</div>';
                return;
            }
            
            const pending = data.announcements.filter(a => a.announcement_status === 'pending' || (!a.announcement_status && a.account_type === 'faculty'));
            const approved = data.announcements.filter(a => a.announcement_status === 'approved' || a.account_type === 'admin');
            
            let html = '';
            if (pending.length > 0) {
                html += '<h6 class="mt-2 mb-2 text-warning"><i class="fas fa-clock"></i> Pending approval</h6>';
                html += pending.map(a => `
                    <div class="admin-activity-item">
                        <div class="d-flex align-items-start gap-3">
                            <img src="${a.avatar}" class="admin-activity-avatar" onerror="this.src='https://via.placeholder.com/40'">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <strong>${escapeHtml(a.name)}</strong>
                                    <span class="text-muted">@${escapeHtml(a.username)}</span>
                                    <span class="badge bg-warning text-dark">Faculty</span>
                                </div>
                                <div class="admin-activity-content">${escapeHtml(a.content)}</div>
                                <div class="admin-activity-meta">${getTimeAgo(a.created_at)}</div>
                                <div class="admin-activity-actions mt-2">
                                    <button class="btn btn-sm btn-success" onclick="adminApproveAnnouncement(${a.id})">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="adminDeletePost(${a.id})">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `).join('');
            }
            if (approved.length > 0) {
                html += '<h6 class="mt-4 mb-2 text-success"><i class="fas fa-check-circle"></i> Posted announcements</h6>';
                html += approved.map(a => `
                    <div class="admin-activity-item">
                        <div class="d-flex align-items-start gap-3">
                            <img src="${a.avatar}" class="admin-activity-avatar" onerror="this.src='https://via.placeholder.com/40'">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <strong>${escapeHtml(a.name)}</strong>
                                    <span class="text-muted">@${escapeHtml(a.username)}</span>
                                    <span class="badge bg-info">${a.account_type}</span>
                                </div>
                                <div class="admin-activity-content">${escapeHtml(a.content)}</div>
                                <div class="admin-activity-meta">${getTimeAgo(a.created_at)}</div>
                            </div>
                        </div>
                    </div>
                `).join('');
            }
            if (!html) html = '<div class="text-center py-4 text-muted">No announcements</div>';
            container.innerHTML = html;
        })
        .catch(() => { container.innerHTML = '<div class="text-center py-4 text-danger">Error loading announcements</div>'; });
}

function adminApproveAnnouncement(postId) {
    fetch('api/admin_approve_announcement.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ admin_id: currentUser.id, post_id: postId })
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Swal.fire('Approved', 'Announcement is now visible to everyone.', 'success');
                renderAnnouncements();
                loadPosts();
            } else {
                Swal.fire('Error', data.message || 'Failed to approve', 'error');
            }
        })
        .catch(() => Swal.fire('Error', 'Failed to approve', 'error'));
}

function showAdminPostMenu(postId, userId, targetAccountType = 'student', postType = 'post') {
    if (!isModeratorRole()) {
        return;
    }
    const admin = isAdminRole();
    const canWarn = admin || targetAccountType !== 'admin';
    const canDeletePost = admin || (targetAccountType !== 'admin' && postType !== 'announcement');

    if (!canWarn && !canDeletePost) {
        Swal.fire('Not allowed', 'Staff cannot warn admins or remove admin/announcement posts.', 'warning');
        return;
    }
    
    Swal.fire({
        title: admin ? 'Admin Actions' : 'Staff Actions',
        html: `
            <div class="text-start">
                ${canWarn ? `<button class="btn btn-warning w-100 mb-2" onclick="adminWarnUser(${userId}, ${postId}, 'post')">
                    <i class="fas fa-exclamation-triangle"></i> Warn User
                </button>` : ''}
                ${admin ? `<button class="btn btn-danger w-100 mb-2" onclick="adminBanUser(${userId})">
                    <i class="fas fa-ban"></i> Ban User
                </button>` : ''}
                ${admin ? `<button class="btn btn-outline-warning w-100 mb-2" onclick="adminLockUser(${userId})">
                    <i class="fas fa-lock"></i> Lock Account
                </button>` : ''}
                ${canDeletePost ? `<button class="btn btn-outline-danger w-100 mb-2" onclick="adminDeletePost(${postId})">
                    <i class="fas fa-trash"></i> Delete Post
                </button>` : ''}
                ${admin ? `<button class="btn btn-outline-danger w-100" onclick="adminDeleteUser(${userId})">
                    <i class="fas fa-user-times"></i> Delete Account
                </button>` : ''}
            </div>
        `,
        showConfirmButton: false,
        showCancelButton: true,
        cancelButtonText: 'Close'
    });
}

function adminWarnUser(userId, itemId, itemType) {
    Swal.fire({
        title: 'Warn User',
        input: 'textarea',
        inputLabel: 'Reason for warning',
        inputPlaceholder: 'Enter the reason...',
        inputAttributes: {
            'aria-label': 'Reason for warning'
        },
        showCancelButton: true,
        confirmButtonText: 'Warn User',
        confirmButtonColor: '#f59e0b',
        inputValidator: (value) => {
            if (!value) {
                return 'You need to provide a reason!';
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('api/admin_warn_user.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    admin_id: currentUser.id,
                    user_id: userId,
                    reason: result.value
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Success!', 'User has been warned.', 'success');
                    loadAdminData();
                } else {
                    Swal.fire('Error', data.message || 'Failed to warn user', 'error');
                }
            })
            .catch(() => Swal.fire('Error', 'Failed to warn user', 'error'));
        }
    });
}

function adminChangeUserRole(userId, newRole, currentRole) {
    if (!isAdminRole()) {
        Swal.fire('Unauthorized', 'Only admins can change user roles.', 'error');
        return;
    }

    if (newRole === currentRole) {
        return;
    }

    const roleLabel = newRole === 'faculty' ? 'Faculty' : 'Student';
    Swal.fire({
        title: 'Change User Role',
        text: `Set this account role to ${roleLabel}?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Confirm',
        confirmButtonColor: '#2563eb'
    }).then((result) => {
        if (!result.isConfirmed) return;

        fetch('api/admin_chnage_user_role.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                admin_id: currentUser.id,
                user_id: userId,
                account_type: newRole
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Swal.fire('Updated', `Role changed to ${roleLabel}.`, 'success').then(() => {
                    window.location.reload();
                });
            } else {
                Swal.fire('Error', data.message || 'Failed to change role', 'error');
            }
        })
        .catch(() => Swal.fire('Error', 'Failed to change role', 'error'));
    });
}

function adminBanUser(userId) {
    Swal.fire({
        title: 'Ban User',
        html: `
            <div class="text-start">
                <label class="form-label">Duration Value</label>
                <input type="number" id="banDuration" class="form-control mb-3" min="1" value="1">
                <label class="form-label">Duration Unit</label>
                <select id="banUnit" class="form-control mb-3">
                    <option value="minutes">Minutes</option>
                    <option value="hours" selected>Hours</option>
                    <option value="days">Days</option>
                </select>
                <label class="form-label">Reason</label>
                <textarea id="banReason" class="form-control" rows="3" placeholder="Enter reason for ban..."></textarea>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Ban User',
        confirmButtonColor: '#ef4444',
        preConfirm: () => {
            const duration = document.getElementById('banDuration').value;
            const unit = document.getElementById('banUnit').value;
            const reason = document.getElementById('banReason').value;
            if (!reason) {
                Swal.showValidationMessage('Please provide a reason');
                return false;
            }
            return { duration, unit, reason };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('api/admin_ban_user.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    admin_id: currentUser.id,
                    user_id: userId,
                    duration: parseInt(result.value.duration),
                    duration_unit: result.value.unit,
                    reason: result.value.reason
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Success!', `User has been banned until ${new Date(data.banned_until).toLocaleString()}`, 'success');
                    loadAdminData();
                } else {
                    Swal.fire('Error', data.message || 'Failed to ban user', 'error');
                }
            })
            .catch(() => Swal.fire('Error', 'Failed to ban user', 'error'));
        }
    });
}

function adminLockUser(userId) {
    Swal.fire({
        title: 'Lock User Account',
        html: `
            <div class="text-start">
                <label class="form-label">Duration Value</label>
                <input type="number" id="lockDuration" class="form-control mb-3" min="1" value="1">
                <label class="form-label">Duration Unit</label>
                <select id="lockUnit" class="form-control mb-3">
                    <option value="minutes">Minutes</option>
                    <option value="hours">Hours</option>
                    <option value="days" selected>Days</option>
                </select>
                <label class="form-label">Reason</label>
                <textarea id="lockReason" class="form-control" rows="3" placeholder="Enter reason for lock (e.g., sensitive and not reliable content)..."></textarea>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Lock Account',
        confirmButtonColor: '#f59e0b',
        preConfirm: () => {
            const duration = document.getElementById('lockDuration').value;
            const unit = document.getElementById('lockUnit').value;
            const reason = document.getElementById('lockReason').value;
            if (!reason) {
                Swal.showValidationMessage('Please provide a reason');
                return false;
            }
            return { duration, unit, reason };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('api/admin_lock_user.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    admin_id: currentUser.id,
                    user_id: userId,
                    duration: parseInt(result.value.duration),
                    duration_unit: result.value.unit,
                    reason: result.value.reason
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Success!', `User account has been locked until ${new Date(data.lock_until).toLocaleString()}`, 'success');
                    loadAdminData();
                } else {
                    Swal.fire('Error', data.message || 'Failed to lock user account', 'error');
                }
            })
            .catch(() => Swal.fire('Error', 'Failed to lock user account', 'error'));
        }
    });
}

function adminDeletePost(postId) {
    Swal.fire({
        title: 'Delete Post?',
        text: "This action cannot be undone!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6366f1',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('api/admin_delete_post.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    admin_id: currentUser.id,
                    post_id: postId
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Deleted!', 'Post has been deleted.', 'success');
                    loadAdminData();
                    loadPosts();
                    loadMyProfileStats();
                } else {
                    Swal.fire('Error', data.message || 'Failed to delete post', 'error');
                }
            })
            .catch(() => Swal.fire('Error', 'Failed to delete post', 'error'));
        }
    });
}

function adminDeleteUser(userId) {
    Swal.fire({
        title: 'Delete User Account?',
        text: "This will permanently delete the user and all their data!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6366f1',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('api/admin_delete_user.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    admin_id: currentUser.id,
                    user_id: userId
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Deleted!', 'User account has been deleted.', 'success');
                    loadAdminData();
                } else {
                    Swal.fire('Error', data.message || 'Failed to delete user', 'error');
                }
            })
            .catch(() => Swal.fire('Error', 'Failed to delete user', 'error'));
        }
    });
}

function renderReports() {
    const container = document.getElementById('reportsList');
    if (!container) return;
    
    container.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
    
    fetch(`api/get_reports.php?moderator_id=${currentUser.id}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.reports || data.reports.length === 0) {
                container.innerHTML = '<div class="text-center py-4 text-muted">No reports found</div>';
                return;
            }
            
            const isStaff = isStaffRole();
            container.innerHTML = data.reports.map(report => {
                const timeAgo = getTimeAgo(report.created_at);
                const canRemove = !isStaff || (report.post_owner_account_type !== 'admin' && report.post_type !== 'announcement');
                const showStatusBadge = !(isStaff && !canRemove);
                return `
                    <div class="admin-report-item">
                        <div class="d-flex align-items-start gap-3">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <strong>Reported by:</strong>
                                    <span>${escapeHtml(report.reporter_name)} (@${escapeHtml(report.reporter_username)})</span>
                                    ${showStatusBadge ? `<span class="badge bg-warning text-dark">${report.status}</span>` : ''}
                                </div>
                                <div class="admin-report-content mb-2">
                                    <strong>Post Content:</strong>
                                    <p class="mb-1">${escapeHtml(report.post_content)}</p>
                                </div>
                                <div class="admin-report-reason mb-2">
                                    <strong>Reason:</strong>
                                    <p class="mb-1">${escapeHtml(report.reason)}</p>
                                </div>
                                <div class="admin-report-meta mb-2">
                                    <small class="text-muted">${timeAgo}</small>
                                </div>
                                <div class="admin-report-actions">
                                    ${canRemove ? `<button class="btn btn-sm btn-outline-danger" onclick="removeReportedPost(${report.post_id}, ${report.id}, '${report.post_owner_account_type || 'student'}', '${report.post_type || 'post'}')">
                                        <i class="fas fa-trash"></i> Remove Post
                                    </button>` : ''}
                                    <button class="btn btn-sm btn-outline-secondary" onclick="dismissReport(${report.id})">
                                        <i class="fas fa-times"></i> Dismiss
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        })
        .catch(err => {
            container.innerHTML = '<div class="text-center py-4 text-danger">Error loading reports</div>';
        });
}

function renderLogs() {
    const container = document.getElementById('logsList');
    if (!container) return;
    
    container.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
    
    fetch(`api/get_logs.php?admin_id=${currentUser.id}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.logs || data.logs.length === 0) {
                container.innerHTML = '<div class="text-center py-4 text-muted">No logs found</div>';
                return;
            }
            
            container.innerHTML = `
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Action</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.logs.map(log => `
                                <tr>
                                    <td>${getTimeAgo(log.created_at)}</td>
                                    <td><span class="badge bg-primary">${escapeHtml(log.action)}</span></td>
                                    <td>${escapeHtml(log.details)}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        })
        .catch(err => {
            container.innerHTML = '<div class="text-center py-4 text-danger">Error loading logs</div>';
        });
}

function removeReportedPost(postId, reportId, ownerType = 'student', postType = 'post') {
    if (isStaffRole() && (ownerType === 'admin' || postType === 'announcement')) {
        Swal.fire('Not allowed', 'Staff cannot remove admin or announcement posts.', 'warning');
        return;
    }
    Swal.fire({
        title: 'Remove Reported Post?',
        text: "This will delete the post and resolve the report.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6366f1',
        confirmButtonText: 'Yes, remove it!'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('api/remove_post.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    admin_id: currentUser.id,
                    post_id: postId,
                    reason: 'Removed due to report'
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Removed!', 'Post has been removed.', 'success');
                    renderReports();
                    loadPosts();
                    loadMyProfileStats();
                } else {
                    Swal.fire('Error', data.message || 'Failed to remove post', 'error');
                }
            })
            .catch(() => Swal.fire('Error', 'Failed to remove post', 'error'));
        }
    });
}

function dismissReport(reportId) {
    // This would require a backend endpoint to dismiss reports
    Swal.fire('Info', 'Report dismissal feature coming soon', 'info');
}
