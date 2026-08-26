/**
 * Re-exported from mhm/ui-core so the existing import path keeps working.
 *
 * Known defect carried by the shared implementation: the mount effect calls
 * setLoading( true ) even when initialData was supplied, so passing pre-populated
 * data does not avoid a first-paint spinner past the first frame. Behaviour is
 * unchanged from what this file did before the move; fixing it is its own round.
 */
export { useApi } from '../../../vendor/mhm/ui-core/src-react/hooks/useApi';
