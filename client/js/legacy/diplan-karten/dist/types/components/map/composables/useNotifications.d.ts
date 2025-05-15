/**
 * This composable provides some oruga notification message functions
 * for a standardized usage through the whole project.
 */
export declare const showErrorAlert: (message: string) => void;
export declare const showSuccessAlert: (message: string) => void;
export declare const showInfoAlert: (message: string) => void;
export declare const showWarningAlert: (message: string) => void;
declare const notifications: {
    showInfo: (message: string) => void;
    showWarning: (message: string) => void;
    showSuccess: (message: string) => void;
    showError: (message: string) => void;
};
export declare const useNotifications: () => typeof notifications;
export {};
