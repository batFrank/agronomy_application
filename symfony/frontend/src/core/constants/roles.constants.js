export const ROLES = {
  ADMIN: 'ROLE_ADMIN',
  MANAGER: 'ROLE_MANAGER',
  USER: 'ROLE_USER',
}

// Ordered from highest to lowest privilege. Used to resolve a user's primary role.
export const ROLE_HIERARCHY = [ROLES.ADMIN, ROLES.MANAGER, ROLES.USER]
