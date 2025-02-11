using Microsoft.EntityFrameworkCore;
using UserApi.Models;

namespace UserApi.Data
{
    public class ApplicationDbContext : DbContext
    {
        public ApplicationDbContext(DbContextOptions<ApplicationDbContext> options) : base(options) { }

        public DbSet<Utilisateur> Utilisateurs { get; set; }

        protected override void OnModelCreating(ModelBuilder modelBuilder)
        {
            base.OnModelCreating(modelBuilder);

            modelBuilder.Entity<Utilisateur>(entity =>
            {
                entity.ToTable("utilisateur");

                entity.Property(u => u.Id)
                    .HasColumnName("id");

                entity.Property(u => u.Nom)
                    .HasColumnName("nom")
                    .IsRequired();

                entity.Property(u => u.Email)
                    .HasColumnName("email")
                    .IsRequired();

                entity.Property(u => u.PasswordHash)
                    .HasColumnName("password")
                    .IsRequired();

                entity.Property(u => u.VerificationToken)
                    .HasColumnName("verification_token")
                    .HasDefaultValue("")  // Définir une valeur par défaut
                    .IsRequired();  // La colonne est requise  // Permettre NULL

                entity.Property(u => u.EmailVerified)
                    .HasColumnName("email_verified")
                    .HasDefaultValue(false);

                entity.Property(u => u.FailedLoginAttempts)
                    .HasColumnName("failed_login_attempts")
                    .HasDefaultValue(0);
            });
        }
    }
}
