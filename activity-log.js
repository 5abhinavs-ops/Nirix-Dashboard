// activity-log.js
// Central activity logger — writes to Firestore activity_log collection
// Requires Firebase Firestore already initialised as _fbDb in index_base.html
// Call logActivity(action, detail) anywhere in the dashboard

const SESSION_ID = Math.random().toString(36).slice(2, 10);

function getNrixSessionId() {
  return SESSION_ID;
}

async function logActivity(action, detail) {
  try {
    if (typeof _fbDb === 'undefined' || !_fbDb) return;
    const user = getCurrentUser();
    if (!user.uid) return;
    await _fbDb.collection('activity_log').add({
      uid:       user.uid,
      email:     user.email,
      role:      user.role,
      action:    action,
      detail:    detail || '',
      sessionId: SESSION_ID,
      timestamp: firebase.firestore.FieldValue.serverTimestamp()
    });
  } catch(e) {
    console.warn('logActivity failed:', e);
  }
}
