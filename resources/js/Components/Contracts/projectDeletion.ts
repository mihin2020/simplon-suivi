/**
 * Warning shown before deleting a contract project: its contracts go with it,
 * so the count is spelled out.
 */
export const projectDeletionMessage = (name: string, contractsCount: number): string => {
    if (contractsCount === 0) {
        return `Supprimer le projet « ${name} » ? Il ne contient aucun contrat.`
    }

    const contracts = contractsCount === 1
        ? 'son contrat sera aussi supprimé'
        : `ses ${contractsCount} contrats seront aussi supprimés`

    return `Attention : en supprimant le projet « ${name} », ${contracts}, `
        + 'y compris ceux déjà finalisés ou envoyés par email. Ils ne seront plus accessibles dans la plateforme.'
}
