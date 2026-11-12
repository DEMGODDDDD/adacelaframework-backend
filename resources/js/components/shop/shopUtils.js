export const FALLBACK_IMAGE = '/images/goldfish.png';
export const currency = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });

export const getAvatarUrl = (avatar) => {
  if (!avatar) return undefined;
  if (/^(https?:|data:|blob:|\/)/i.test(avatar)) return avatar;
  return `/storage/${avatar}`;
};
