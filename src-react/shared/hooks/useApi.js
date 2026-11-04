/**
 * Re-exported from mhm/ui-core so the existing import path keeps working.
 *
 * Pre-populated initialData renders without a spinner (it is refreshed
 * quietly), and an answer that settles after a newer request is ignored
 * (ui-core 0.20.0).
 */
export { useApi } from '../../../vendor/mhm/ui-core/src-react/hooks/useApi';
