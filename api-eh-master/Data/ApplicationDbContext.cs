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

                entity.HasKey(u => u.Id);

                entity.Property(u => u.Id)
                    .HasColumnName("id")
                    .ValueGeneratedOnAdd();

                entity.Property(u => u.Nom)
                    .HasColumnName("nom")
                    .IsRequired()
                    .HasMaxLength(255);

                entity.Property(u => u.PasswordHash)
                    .HasColumnName("password")
                    .IsRequired()
                    .HasMaxLength(255);

                entity.Property(u => u.Email)
                    .HasColumnName("email")
                    .IsRequired()
                    .HasMaxLength(255);

                entity.Property(u => u.FailedLoginAttempts)
                    .HasColumnName("failed_login_attempts")  // Correspond au nom de la colonne dans la base de données
                    .HasDefaultValue(0);
            });
        }
    }
}
