// permissions.js v2
// Three-way lookup: explicit grant (true) > explicit deny (false) > role default
// Call initPermissions(role, extraPermissions) after login.
// extraPermissions is a flat map — any key set to true = granted, false = denied, absent/null = role default

let _role = null;
let _extras = {};
let _email = '';
let _uid = '';

function initPermissions(role, extras, email, uid) {
  _role  = role  || 'viewer';
  _extras = extras || {};
  _email = email || '';
  _uid   = uid   || '';
}

function _check(key, roleDefault) {
  if (_extras[key] === true)  return true;
  if (_extras[key] === false) return false;
  return roleDefault;
}

const can = {
  // Certification
  editCertName:    () => _check('editCertName',    _role === 'admin' || _role === 'manager'),
  editExpiry:      () => _check('editExpiry',      _role === 'admin' || _role === 'manager'),
  editRenewal:     () => _check('editRenewal',     _role === 'admin' || _role === 'manager'),
  uploadCert:      () => _check('uploadCert',      _role === 'admin' || _role === 'manager'),
  downloadCert:    () => _check('downloadCert',    true),
  deleteCert:      () => _check('deleteCert',      _role === 'admin'),
  addCert:         () => _check('addCert',         _role === 'admin' || _role === 'manager'),
  // Running hours
  editRunHrs:      () => _check('editRunHrs',      _role === 'admin' || _role === 'manager'),
  // Boat tech specs
  editBoatSpecs:   () => _check('editBoatSpecs',   _role === 'admin'),
  // Fleet availability edit mode
  editFleet:       () => _check('editFleet',       _role === 'admin'),
  // Admin panel
  accessAdminPanel:() => _role === 'admin',
};

// Expose current user info for activity logging
function getCurrentUser() { return { uid: _uid, email: _email, role: _role }; }
