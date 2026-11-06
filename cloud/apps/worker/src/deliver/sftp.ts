export async function deliverSftp(): Promise<never> {
  throw new Error("SFTP não implementado no MVP; use delivery=rest");
}
