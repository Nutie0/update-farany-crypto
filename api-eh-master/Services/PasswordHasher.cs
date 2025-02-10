using System;
using System.Linq;
using System.Security.Cryptography;
using System.Text;


namespace UserApi.Services
{
    public class PasswordHasher
    {
        private const int SaltSize = 16;
        private const int Iterations = 10000;
        private const int HashSize = 32;

        public string HashPassword(string password)
        {
            using var rng = RandomNumberGenerator.Create(); // Remplacer RNGCryptoServiceProvider par RandomNumberGenerator
            var salt = new byte[SaltSize];
            rng.GetBytes(salt); // Génère le sel

            using var pbkdf2 = new Rfc2898DeriveBytes(password, salt, Iterations, HashAlgorithmName.SHA256); // Correctement utiliser Rfc2898DeriveBytes avec SHA256
            var hash = pbkdf2.GetBytes(HashSize); // Obtient le hachage

            // Combine le sel et le hachage dans un tableau
            var hashBytes = new byte[SaltSize + HashSize];
            Array.Copy(salt, 0, hashBytes, 0, SaltSize);
            Array.Copy(hash, 0, hashBytes, SaltSize, HashSize);

            return Convert.ToBase64String(hashBytes); // Retourne le hachage sous forme de chaîne Base64
        }

        public bool VerifyPassword(string password, string hashedPassword)
        {
            try
            {
                // Convertit le mot de passe haché de la base64 à un tableau de bytes
                var hashBytes = Convert.FromBase64String(hashedPassword);

                var salt = new byte[SaltSize];
                Array.Copy(hashBytes, 0, salt, 0, SaltSize); // Extrait le sel du hachage

                var storedHash = new byte[HashSize];
                Array.Copy(hashBytes, SaltSize, storedHash, 0, HashSize); // Extrait le hachage stocké

                // Calcule un nouveau hachage en utilisant le même sel
                using var pbkdf2 = new Rfc2898DeriveBytes(password, salt, Iterations, HashAlgorithmName.SHA256);
                var computedHash = pbkdf2.GetBytes(HashSize);

                return storedHash.SequenceEqual(computedHash); // Compare les hachages
            }
            catch (FormatException)
            {
                return false;
            }
            catch (Exception)
            {
                return false;
            }
        }
    }
}
